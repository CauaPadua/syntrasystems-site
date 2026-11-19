<?php
/**
 * Aquapulse — a estação de telemetria (datalogger) de uma represa.
 *
 * Na barragem, os sensores ficam ligados por cabo a esta estação. É ela que:
 *   1. lê os sensores ligados a ela                            (coletar)
 *   2. guarda cada leitura com o código do equipamento e o horário da medição
 *   3. envia o que está na memória em um único pacote JSON      (transmitir)
 *   4. confere a resposta e tira da memória o que o servidor confirmou
 *
 * A memória garante que nenhum dado se perde: se o sinal cai ou o servidor sai
 * do ar, a estação continua lendo e guardando; quando a comunicação volta, tudo
 * vai no próximo envio, cada leitura com o seu horário original.
 *
 * A resposta do servidor é conferida leitura por leitura:
 *   gravada            sai da memória;
 *   recusada (4xx)     erro da própria leitura (ex.: valor impossível): é descartada;
 *   repetida           já tinha chegado antes (a confirmação se perdeu): sai da memória;
 *   falha (5xx)        problema no servidor: fica na memória e vai de novo no próximo ciclo.
 */

declare(strict_types=1);

final class Estacao
{
    /** Leituras que cabem na memória; quando enche, as mais antigas são descartadas. */
    private const MEMORIA_MAXIMA = 5000;

    /** Leituras por pacote (limite de api/v1/ingest/batch.php). */
    private const LEITURAS_POR_PACOTE = 500;

    private const SITUACAO = [
        'falha'        => 'sem resposta (falha no sensor)',
        'pausado'      => 'pausado no simulador',
        'ausente'      => 'não existe no simulador (use "Sincronizar com o sistema")',
        'sem-resposta' => 'simulador fora do ar',
    ];

    public string $id;
    public string $represaNome;
    /** @var list<array<string,mixed>> equipamentos ligados: id, type, type_label, unit, decimals (do cadastro) */
    public array $equipamentos;
    public bool $comSinal = true;
    /** Sensores que não responderam no último ciclo, ou null. */
    public ?string $aviso = null;
    /** Leituras que entraram na memória no último ciclo. */
    public int $lidasNoCiclo = 0;

    /** @var list<array{equipment_id:string,recorded_at:string,value:float}> */
    private array $memoria = [];
    private DateTimeImmutable $ligadaEm;

    /** @param list<array<string,mixed>> $equipamentos */
    public function __construct(string $id, string $represaNome, array $equipamentos, DateTimeImmutable $ligadaEm)
    {
        $this->id = $id;
        $this->represaNome = $represaNome;
        $this->equipamentos = $equipamentos;
        $this->ligadaEm = $ligadaEm;
    }

    /** Identificação que vai em cada envio: o servidor usa para perceber sensores parados. */
    private function identificacao(): array
    {
        return [
            'id'         => $this->id,
            'started_at' => $this->ligadaEm->format(DATE_ATOM),
            'equipment'  => array_values(array_map(static fn (array $e): string => (string) $e['id'], $this->equipamentos)),
        ];
    }

    /**
     * Um ciclo de leitura: retira do leitor as leituras novas de cada sensor
     * desta estação e as guarda na memória, com o horário em que foram medidas.
     *
     * @return array<string,?float> último valor de cada equipamento (null = não respondeu)
     */
    public function coletar(LeitorDeSensores $leitor): array
    {
        $this->lidasNoCiclo = 0;
        $lidas = [];
        $parados = [];

        foreach ($this->equipamentos as $e) {
            $codigo = (string) $e['id'];

            foreach ($leitor->retirar($codigo) as $l) {
                $this->memoria[] = ['equipment_id' => $codigo, 'recorded_at' => $l['recorded_at'], 'value' => $l['value']];
                $this->lidasNoCiclo++;
            }

            $estado = $leitor->estado($codigo);
            $lidas[$codigo] = $estado === 'ok' ? $leitor->atual($codigo) : null;
            if ($estado !== 'ok') {
                $parados[self::SITUACAO[$estado] ?? $estado][] = $codigo;
            }
        }

        if (count($this->memoria) > self::MEMORIA_MAXIMA) {
            $this->memoria = array_slice($this->memoria, -self::MEMORIA_MAXIMA);
        }

        $avisos = [];
        foreach ($parados as $situacao => $codigos) {
            $avisos[] = (count($codigos) === count($this->equipamentos) ? 'todos os sensores' : implode(', ', $codigos)) . ': ' . $situacao;
        }
        $this->aviso = $avisos === [] ? null : implode('; ', $avisos);

        return $lidas;
    }

    /** Quantas leituras esperam confirmação do servidor. */
    public function naMemoria(): int
    {
        return count($this->memoria);
    }

    /**
     * Envia a memória para o servidor e confere a resposta de cada leitura.
     *
     * @return array{situacao:string, erro:string, pacotes:list<array>, gravadas:list<array>, recusadas:list<array>,
     *               repetidas:int, comunicacao:list<array>}   situacao: 'enviado' | 'sem-sinal' | 'falha'
     */
    public function transmitir(Api $api): array
    {
        $resultado = ['situacao' => 'enviado', 'erro' => '', 'pacotes' => [], 'gravadas' => [], 'recusadas' => [], 'repetidas' => 0, 'comunicacao' => []];

        if (!$this->comSinal) {
            $resultado['situacao'] = 'sem-sinal';
            return $resultado;
        }

        $ficam = [];                                                   // leituras que continuam na memória

        // Sem leituras novas, envia só a identificação: o aviso de "estação ligada".
        $pacotes = $this->memoria === [] ? [[]] : array_chunk($this->memoria, self::LEITURAS_POR_PACOTE);

        foreach ($pacotes as $pacote) {
            if ($resultado['situacao'] === 'falha') {                  // o servidor já falhou neste ciclo: o resto espera
                array_push($ficam, ...$pacote);
                continue;
            }

            if ($pacote !== []) {
                $resultado['pacotes'][] = $pacote;
            }
            $resposta = $api->enviar($pacote, $this->identificacao());

            if ($resposta['status'] !== 200) {
                $resultado['situacao'] = 'falha';
                $resultado['erro'] = $resposta['status'] === 0
                    ? 'o servidor não respondeu'
                    : 'HTTP ' . $resposta['status'] . ' ' . ($resposta['body']['error']['message'] ?? '');
                array_push($ficam, ...$pacote);
                continue;
            }

            $dados = $resposta['body']['data'] ?? [];
            array_push($resultado['comunicacao'], ...($dados['communication_alerts'] ?? []));

            $conferidas = [];
            foreach ($dados['results'] ?? [] as $r) {
                $leitura = $pacote[$r['index']] ?? null;
                if ($leitura === null) {
                    continue;
                }

                if ($r['ok']) {
                    $resultado['gravadas'][] = $leitura + ['classification' => $r['classification'], 'alert_id' => $r['alert_id']];
                } elseif ($r['code'] === 'DUPLICATE_READING') {
                    $resultado['repetidas']++;
                } elseif ($r['status'] >= 500) {
                    continue;                                          // não conferida: fica na memória
                } else {
                    $resultado['recusadas'][] = $leitura + ['status' => $r['status'], 'code' => $r['code'], 'message' => $r['message']];
                }
                $conferidas[$r['index']] = true;
            }

            foreach ($pacote as $i => $leitura) {                      // sem confirmação do servidor: tenta de novo depois
                if (!isset($conferidas[$i])) {
                    $ficam[] = $leitura;
                }
            }
        }

        $this->memoria = $ficam;
        return $resultado;
    }
}
