<?php
/**
 * Simulador de sensores — os equipamentos de campo das três represas.
 *
 * Programa independente do site e da estação de telemetria: faz o papel dos
 * sensores físicos instalados nas represas. Ele só GERA leituras e as
 * DISPONIBILIZA em um endpoint local; quem vem buscar é a estação. Não conhece
 * o banco de dados.
 *
 * Os sensores vêm do CADASTRO DO SISTEMA (tabela sensors, lida pela API de
 * ingestão com a mesma chave das estações): o simulador cria um sensor para cada
 * equipamento ativo e começa do último valor gravado no banco, então simulador,
 * banco e painel partem dos mesmos números. Equipamento novo cadastrado no CRUD
 * aparece depois de "Sincronizar com o sistema". Se o site estiver desligado, usa
 * uma lista padrão (18 sensores, 6 por represa) e tenta de novo a cada 20 s.
 *
 * Os LIMITES (Atenção, Crítico, faixa ideal) também vêm do sistema, para cada
 * sensor: o nível da Rio Verde não tem os mesmos limites do da Santa Clara. Quem
 * muda um limite no painel clica em "Sincronizar" para o simulador ler os novos.
 *
 * Algoritmo de cada sensor (a cada "intervalo" segundos, 5 por padrão):
 *   modo automático   valor = valor anterior + número aleatório entre -variação e +variação,
 *                     limitado à faixa configurada
 *                     (pluviômetro: em cada leitura há uma chance de chover de 0,2 a 0,8 mm)
 *   modo manual       repete o valor escolhido na tela (para provocar alertas)
 *   registra código, valor e horário -> disponibiliza para a estação
 *
 * Falhas que a tela pode simular em cada sensor:
 *   sem-resposta      o sensor para de medir: a estação fica sem leituras dele e,
 *                     depois de 2 min, o sistema abre o alerta "Equipamento sem leituras";
 *   valor-impossivel  a cada 3 leituras o sensor manda -5 (fora da faixa física):
 *                     a API recusa essa leitura e ela não chega ao banco.
 *
 * O servidor embutido do PHP atende um pedido por vez e não tem tarefa em
 * segundo plano; por isso, a cada pedido, cada sensor produz as leituras cujo
 * horário já chegou (uma por intervalo, cada uma com o seu horário exato). Para
 * quem consulta, é igual a sensores que medem sozinhos.
 *
 * O estado fica em dados/estado.json (apagado toda vez que o simulador é ligado).
 */

declare(strict_types=1);

final class Sensores
{
    private const MAX_LEITURAS = 1500;    // fila de leituras para a estação (todas os sensores)
    private const MAX_HIST = 60;          // pontos do gráfico de cada sensor
    private const MAX_LOG = 100;
    private const MAX_ATRASO = 120;       // mais leituras atrasadas que isto = o simulador ficou parado, não recupera
    private const NOVA_TENTATIVA = 20;    // segundos entre tentativas de ler o cadastro quando o site está desligado
    private const TIPOS_ACEITOS = ['level', 'inflow', 'outflow', 'ph', 'water_temp', 'precipitation'];
    private const FALHAS = ['', 'sem-resposta', 'valor-impossivel'];
    private const VALOR_IMPOSSIVEL = -5.0;   // fora da faixa de qualquer grandeza: a API recusa

    private string $arquivo;

    public function __construct(string $arquivo)
    {
        $this->arquivo = $arquivo;
    }

    /* ========================================================== cadastro */

    /**
     * Represas, valores normais, códigos dos sensores e limites do nível (atenção e
     * crítico), iguais aos do banco. Só é usado com o site desligado.
     *
     * @return array<string,array{0:string,1:array<string,float>,2:array<string,string>,3:array{0:float,1:float}}>
     */
    private static function represas(): array
    {
        return [
            'santa-clara' => ['Represa Santa Clara',
                ['level' => 82.4, 'inflow' => 56.2, 'outflow' => 49.8, 'ph' => 7.2, 'water_temp' => 22.6],
                ['level' => 'SEN-NIV-01', 'inflow' => 'SEN-VAZ-01', 'outflow' => 'SEN-VAZ-02', 'ph' => 'SEN-PH-01', 'water_temp' => 'SEN-TMP-01', 'precipitation' => 'SEN-PLU-01'],
                [78.0, 85.0]],
            'rio-verde' => ['Represa Rio Verde',
                ['level' => 64.1, 'inflow' => 43.8, 'outflow' => 39.6, 'ph' => 7.4, 'water_temp' => 22.1],
                ['level' => 'SEN-NIV-11', 'inflow' => 'SEN-VAZ-11', 'outflow' => 'SEN-VAZ-12', 'ph' => 'SEN-PH-11', 'water_temp' => 'SEN-TMP-11', 'precipitation' => 'SEN-PLU-11'],
                [68.4, 82.1]],
            'serra-azul' => ['Represa Serra Azul',
                ['level' => 69.3, 'inflow' => 32.5, 'outflow' => 29.1, 'ph' => 7.3, 'water_temp' => 21.8],
                ['level' => 'SEN-NIV-21', 'inflow' => 'SEN-VAZ-21', 'outflow' => 'SEN-VAZ-22', 'ph' => 'SEN-PH-21', 'water_temp' => 'SEN-TMP-21', 'precipitation' => 'SEN-PLU-21'],
                [72.3, 83.6]],
        ];
    }

    /**
     * Limites usados com o site desligado, no mesmo formato em que a API os entrega.
     *
     * @param array{0:float,1:float} $nivel atenção e crítico do nível da represa
     * @return array<string,array{min:float,max:?float}>
     */
    private static function limitesPadrao(string $tipo, array $nivel): array
    {
        switch ($tipo) {
            case 'level':
                return ['attention' => ['min' => $nivel[0], 'max' => $nivel[1]], 'critical' => ['min' => $nivel[1], 'max' => null]];
            case 'ph':
                return ['normal' => ['min' => 6.5, 'max' => 8.5]];
            case 'precipitation':
                return ['attention' => ['min' => 20.0, 'max' => 60.0], 'critical' => ['min' => 60.0, 'max' => null]];
            default:
                return [];
        }
    }

    /**
     * Características de cada tipo de sensor.
     *   abs_min/abs_max  o que o sensor consegue medir (fora disso a API recusaria)
     *   variacao         variação máxima por leitura no modo automático (no pluviômetro: chance de chuva, em %)
     *   presets          atalhos da tela para o modo manual (calculados dos limites do sensor)
     *
     * @param array<string,array{min:?float,max:?float}> $faixas limites do sensor (API do sistema ou limitesPadrao)
     * @return array<string,mixed>
     */
    private static function tipo(string $tipo, float $inicial, array $faixas): array
    {
        $base = ['faixa_min' => null, 'faixa_max' => null, 'intervalo' => 5];
        $atencao = isset($faixas['attention']['min']) ? (float) $faixas['attention']['min'] : null;
        $critico = isset($faixas['critical']['min']) ? (float) $faixas['critical']['min'] : null;

        switch ($tipo) {
            case 'level':
                return $base + ['nome' => 'Nível do reservatório', 'equip' => 'Régua de nível', 'unidade' => '%', 'casas' => 1,
                    'abs_min' => 0.0, 'abs_max' => 100.0, 'variacao' => 0.1, 'var_max' => 5.0, 'rotulo_var' => 'Variação por leitura (±)',
                    'limites' => $atencao === null || $critico === null ? 'Sem limite cadastrado'
                        : 'Atenção a partir de ' . self::txt($atencao) . ' % (cota de alerta), crítico a partir de ' . self::txt($critico) . ' % (cota de vertimento)',
                    'presets' => $atencao === null || $critico === null ? [['v' => $inicial, 'r' => 'normal']]
                        : [['v' => max(0.0, floor($atencao) - 5), 'r' => 'normal'], ['v' => round(($atencao + $critico) / 2, 1), 'r' => 'atenção'],
                           ['v' => min(100.0, ceil($critico) + 2), 'r' => 'crítico']]];
            case 'inflow':
                return $base + ['nome' => 'Vazão que entra', 'equip' => 'Afluência principal', 'unidade' => 'm³/s', 'casas' => 1,
                    'abs_min' => 0.0, 'abs_max' => 5000.0, 'variacao' => 0.4, 'var_max' => 100.0, 'rotulo_var' => 'Variação por leitura (±)',
                    'limites' => 'Sem limite de alerta: a cheia aparece no nível',
                    'presets' => [['v' => $inicial, 'r' => 'normal'], ['v' => round($inicial * 4, 1), 'r' => 'cheia'], ['v' => round($inicial * 10, 1), 'r' => 'enchente']]];
            case 'outflow':
                return $base + ['nome' => 'Vazão que sai', 'equip' => 'Defluência principal', 'unidade' => 'm³/s', 'casas' => 1,
                    'abs_min' => 0.0, 'abs_max' => 5000.0, 'variacao' => 0.3, 'var_max' => 100.0, 'rotulo_var' => 'Variação por leitura (±)',
                    'limites' => 'Sem limite de alerta',
                    'presets' => [['v' => $inicial, 'r' => 'normal'], ['v' => round($inicial * 2, 1), 'r' => 'comportas abertas'], ['v' => round($inicial * 6, 1), 'r' => 'vertedouro']]];
            case 'ph':
                return $base + ['nome' => 'pH da água', 'equip' => 'Sonda de pH', 'unidade' => 'pH', 'casas' => 2,
                    'abs_min' => 0.0, 'abs_max' => 14.0, 'variacao' => 0.02, 'var_max' => 1.0, 'rotulo_var' => 'Variação por leitura (±)',
                    'limites' => isset($faixas['normal']) ? 'Faixa ideal de ' . self::txt((float) $faixas['normal']['min']) . ' a ' . self::txt((float) $faixas['normal']['max']) . '; fora dela, atenção' : 'Sem limite cadastrado',
                    'presets' => !isset($faixas['normal']) ? [['v' => $inicial, 'r' => 'normal']]
                        : [['v' => round(($faixas['normal']['min'] + $faixas['normal']['max']) / 2, 1), 'r' => 'ideal'],
                           ['v' => max(0.0, round($faixas['normal']['min'] - 0.5, 2)), 'r' => 'ácido'],
                           ['v' => min(14.0, round($faixas['normal']['max'] + 0.7, 2)), 'r' => 'alcalino']]];
            case 'water_temp':
                return $base + ['nome' => 'Temperatura da água', 'equip' => 'Termômetro da água', 'unidade' => '°C', 'casas' => 1,
                    'abs_min' => 0.0, 'abs_max' => 45.0, 'variacao' => 0.05, 'var_max' => 2.0, 'rotulo_var' => 'Variação por leitura (±)',
                    'limites' => 'Sem limite de alerta',
                    'presets' => [['v' => $inicial, 'r' => 'normal'], ['v' => 30, 'r' => 'quente'], ['v' => 14, 'r' => 'fria']]];
            default:                                                      // precipitation
                return $base + ['nome' => 'Chuva', 'equip' => 'Pluviômetro basculante', 'unidade' => 'mm', 'casas' => 1,
                    'abs_min' => 0.0, 'abs_max' => 200.0, 'variacao' => 3.0, 'var_max' => 100.0, 'rotulo_var' => 'Chance de chuva por leitura (%)',
                    'limites' => $atencao === null || $critico === null ? 'Sem limite cadastrado'
                        : 'O servidor soma 24 h: atenção a partir de ' . self::txt($atencao) . ' mm, crítico a partir de ' . self::txt($critico) . ' mm',
                    'presets' => [['v' => 0, 'r' => 'sem chuva'], ['v' => 1, 'r' => 'chuva fraca'], ['v' => 5, 'r' => 'temporal']]];
        }
    }

    /* ========================================================== cadastro do sistema */

    /** Endereço do site (o mesmo que a estação usa). */
    public static function enderecoDoSistema(): string
    {
        return rtrim((string) (getenv('AQUAPULSE_URL') ?: 'http://localhost:8000'), '/');
    }

    /** Chave das estações: variável de ambiente ou backend/.env do projeto. */
    private static function chaveDoSistema(): string
    {
        if (getenv('INGEST_API_KEY')) {
            return (string) getenv('INGEST_API_KEY');
        }
        $env = dirname(__DIR__, 2) . '/backend/.env';
        if (is_file($env) && preg_match('/^INGEST_API_KEY=(.+)$/m', (string) file_get_contents($env), $m)) {
            return trim($m[1]);
        }
        return '';
    }

    /**
     * Equipamentos cadastrados no sistema, com o último valor gravado de cada
     * grandeza (GET /api/v1/ingest/equipment.php). Null se o site não respondeu.
     *
     * @return list<array<string,mixed>>|null
     */
    private static function lerCadastro(): ?array
    {
        $chave = self::chaveDoSistema();
        if ($chave === '') {
            return null;
        }

        $ctx = stream_context_create(['http' => [
            'method' => 'GET', 'timeout' => 3, 'ignore_errors' => true,
            'header' => "Accept: application/json\r\nX-Api-Key: {$chave}",
        ]]);
        $resposta = @file_get_contents(self::enderecoDoSistema() . '/api/v1/ingest/equipment.php', false, $ctx);
        $json = $resposta === false ? null : json_decode($resposta, true);
        $lista = $json['data']['equipment'] ?? null;

        return is_array($lista) && $lista !== [] ? $lista : null;
    }

    /**
     * @param array<string,array{min:?float,max:?float}> $faixas limites do sensor
     * @return array<string,mixed> um sensor pronto para simular
     */
    private static function sensor(string $codigo, string $tipo, string $represaId, string $represa, ?string $equip, float $inicial, array $faixas): array
    {
        $t = self::tipo($tipo, $inicial, $faixas);
        return array_merge($t, [
            'faixas' => $faixas,
            'codigo' => $codigo, 'tipo' => $tipo, 'represa_id' => $represaId, 'represa' => $represa,
            'equip' => $equip ?? $t['equip'],
            'faixa_min' => $t['abs_min'], 'faixa_max' => $t['abs_max'],
            'inicial' => $inicial, 'valor' => $inicial,
            'rodando' => true, 'modo' => 'auto', 'falha' => '', 'emitidas' => 0,
            'ultimo_tick' => 0, 'ultima' => null, 'classe' => 'normal',
            'hist' => [], 'sessao' => self::sessaoVazia(),
        ]);
    }

    /**
     * Monta os sensores a partir do cadastro do sistema (ou da lista padrão, se
     * $cadastro for null) e registra no log o que foi feito.
     *
     * @param list<array<string,mixed>>|null $cadastro
     */
    private function montarSensores(array &$s, ?array $cadastro): void
    {
        $s['sensores'] = [];

        if ($cadastro === null) {
            foreach (self::represas() as $represaId => [$represaNome, $valores, $codigos, $nivel]) {
                foreach ($codigos as $tipo => $codigo) {
                    $s['sensores'][$codigo] = self::sensor($codigo, $tipo, $represaId, $represaNome, null, (float) ($valores[$tipo] ?? 0.0), self::limitesPadrao($tipo, $nivel));
                }
            }
            $s['origem'] = 'padrao';
            $this->log($s, 'warn', 'Sistema não encontrado em ' . self::enderecoDoSistema() . ': usando a lista padrão de '
                . count($s['sensores']) . ' sensores. Nova tentativa a cada ' . self::NOVA_TENTATIVA . ' s.');
            return;
        }

        $padrao = ['level' => 70.0, 'inflow' => 40.0, 'outflow' => 36.0, 'ph' => 7.2, 'water_temp' => 22.0, 'precipitation' => 0.0];
        $fora = [];
        foreach ($cadastro as $e) {
            $codigo = (string) $e['id'];
            $tipo = (string) $e['type'];
            if (($e['status'] ?? '') !== 'active' || !in_array($tipo, self::TIPOS_ACEITOS, true)) {
                $fora[] = $codigo;                                   // em manutenção ou desativado: a estação também não lê
                continue;
            }
            // O pluviômetro começa sem chuva; os demais, do último valor gravado no banco.
            $inicial = $tipo === 'precipitation' ? 0.0 : (float) ($e['current_value'] ?? $padrao[$tipo]);
            $s['sensores'][$codigo] = self::sensor($codigo, $tipo, (string) $e['reservoir_id'], (string) $e['reservoir_name'], (string) $e['name'], $inicial, (array) ($e['limits'] ?? []));
        }

        $s['origem'] = 'sistema';
        $this->log($s, 'ok', 'Sincronizado com o sistema: ' . count($s['sensores']) . ' sensores do cadastro, começando dos últimos valores do banco'
            . ($fora === [] ? '.' : '. Fora de operação no cadastro: ' . implode(', ', $fora) . '.'));
    }

    private static function sessaoVazia(): array
    {
        return ['min' => null, 'max' => null, 'soma' => 0.0, 'n' => 0];
    }

    /**
     * Faixa da leitura: 'normal', 'attention' ou 'critical', pelos limites do próprio
     * sensor e pela mesma regra do sistema (StatusRules::classify). A chuva fica de
     * fora: o limite dela vale para a soma de 24 h, que só o servidor conhece.
     *
     * @param array<string,mixed> $x sensor (usa tipo e faixas)
     */
    public static function classificar(array $x, float $valor): string
    {
        $f = $x['tipo'] === 'precipitation' ? [] : ($x['faixas'] ?? []);

        if (isset($f['critical']['min']) && $valor >= $f['critical']['min']) {
            return 'critical';
        }
        if (isset($f['attention']['min']) && $valor >= $f['attention']['min']) {
            return 'attention';
        }
        if (isset($f['normal']['min'], $f['normal']['max']) && ($valor < $f['normal']['min'] || $valor > $f['normal']['max'])) {
            return 'attention';
        }
        return 'normal';
    }

    /* ========================================================== acesso */

    /**
     * Abre o estado com o arquivo travado, deixa os sensores em dia, executa
     * $acao e grava. Devolve o que $acao devolver.
     *
     * @param callable(array<string,mixed>&):mixed $acao
     * @return mixed
     */
    public function executar(callable $acao)
    {
        $pasta = dirname($this->arquivo);
        if (!is_dir($pasta)) {
            mkdir($pasta, 0777, true);
        }

        $f = fopen($this->arquivo, 'c+');
        flock($f, LOCK_EX);

        $estado = json_decode((string) stream_get_contents($f), true);
        if (!is_array($estado) || !isset($estado['sensores'])) {
            // Simulador recém-ligado. "execucao" muda a cada vez que ele é ligado: a
            // estação percebe e recomeça a contagem das leituras.
            $estado = ['execucao' => bin2hex(random_bytes(4)), 'sensores' => [], 'seq' => 0, 'leituras' => [],
                       'consultas' => 0, 'ultima_consulta' => 0, 'log' => [], 'origem' => 'padrao', 'tentativa' => time()];
            $this->log($estado, 'info', 'Simulador ligado.');
            $this->montarSensores($estado, self::lerCadastro());
        } elseif (($estado += ['execucao' => bin2hex(random_bytes(4)), 'origem' => 'padrao', 'tentativa' => 0])['origem'] === 'padrao'
                  && time() - $estado['tentativa'] >= self::NOVA_TENTATIVA) {
            // Ligado antes do site: tenta de novo ler o cadastro do sistema.
            $estado['tentativa'] = time();
            $cadastro = self::lerCadastro();
            if ($cadastro !== null) {
                $this->montarSensores($estado, $cadastro);
            }
        }

        // Estado gravado por uma versão anterior (falha era verdadeiro/falso).
        foreach ($estado['sensores'] as &$x) {
            if (is_bool($x['falha'] ?? '')) {
                $x['falha'] = $x['falha'] ? 'sem-resposta' : '';
            }
        }
        unset($x);

        $this->avancar($estado);
        try {
            $resultado = $acao($estado);
        } finally {
            ftruncate($f, 0);
            rewind($f);
            fwrite($f, (string) json_encode($estado, JSON_UNESCAPED_UNICODE));
            fflush($f);
            flock($f, LOCK_UN);
            fclose($f);
        }
        return $resultado;
    }

    /* ========================================================== relógio */

    /** Cada sensor ligado produz as leituras cujo horário já chegou. */
    private function avancar(array &$s): void
    {
        $agora = time();

        foreach (array_keys($s['sensores']) as $codigo) {
            $x = &$s['sensores'][$codigo];

            if ($x['rodando'] && $x['falha'] !== 'sem-resposta') {
                if ($x['ultimo_tick'] === 0) {
                    $this->emitir($s, $codigo, $agora, false);                // primeira leitura: o valor inicial
                } else {
                    $intervalo = max(1, (int) $x['intervalo']);
                    $atrasadas = intdiv($agora - $x['ultimo_tick'], $intervalo);

                    if ($atrasadas > self::MAX_ATRASO) {
                        $x['ultimo_tick'] = $agora;                           // ficou parado muito tempo: recomeça daqui
                    } else {
                        for ($i = 0; $i < $atrasadas; $i++) {
                            $this->emitir($s, $codigo, $x['ultimo_tick'] + $intervalo, true);
                        }
                    }
                }
            }
            unset($x);
        }
    }

    /** Gera uma leitura do sensor no horário $ts. $variar = aplica o modo automático. */
    private function emitir(array &$s, string $codigo, int $ts, bool $variar): void
    {
        $x = &$s['sensores'][$codigo];

        if ($variar && $x['modo'] === 'auto') {
            if ($x['tipo'] === 'precipitation') {
                $x['valor'] = mt_rand(1, 10000) <= $x['variacao'] * 100 ? mt_rand(1, 4) * 0.2 : 0.0;
            } else {
                $novo = $x['valor'] + (mt_rand(-1000, 1000) / 1000) * $x['variacao'];
                $x['valor'] = round(min((float) $x['faixa_max'], max((float) $x['faixa_min'], $novo)), 3);
            }
        }

        $anterior = $x['ultima']['ts'] ?? 0;
        if ($ts <= $anterior) {
            $ts = $anterior + 1;                                             // nunca dois horários iguais no mesmo sensor
        }

        $valor = round((float) $x['valor'], (int) $x['casas']);
        $x['emitidas'] = ($x['emitidas'] ?? 0) + 1;

        // Defeito "valor impossível": a cada 3 leituras manda -5. Essa leitura vai
        // para a estação (que a envia e vê a API recusar), mas não entra nas
        // estatísticas da sessão nem muda a faixa do sensor.
        $impossivel = $x['falha'] === 'valor-impossivel' && $x['emitidas'] % 3 === 0;
        if ($impossivel) {
            $valor = self::VALOR_IMPOSSIVEL;
        }

        $s['seq']++;
        $x['ultimo_tick'] = $ts;
        $x['ultima'] = ['seq' => $s['seq'], 'value' => $valor, 'ts' => $ts];

        $s['leituras'][] = [$s['seq'], $codigo, $valor, $ts];               // fila para a estação (formato compacto)
        if (count($s['leituras']) > self::MAX_LEITURAS) {
            $s['leituras'] = array_slice($s['leituras'], -self::MAX_LEITURAS);
        }

        $x['hist'][] = [$ts, $valor];
        if (count($x['hist']) > self::MAX_HIST) {
            $x['hist'] = array_slice($x['hist'], -self::MAX_HIST);
        }

        if ($impossivel) {
            $this->log($s, 'erro', "{$codigo}: enviou " . $this->fmt($valor, $x['casas']) . " {$x['unidade']} (valor impossível: a API deve recusar).", $ts);
            unset($x);
            return;
        }

        $r = &$x['sessao'];
        $r['min'] = $r['min'] === null ? $valor : min($r['min'], $valor);
        $r['max'] = $r['max'] === null ? $valor : max($r['max'], $valor);
        $r['soma'] += $valor;
        $r['n']++;
        unset($r);

        // Registra no log só quando a faixa muda (uma linha por leitura encheria a tela).
        $classe = self::classificar($x, $valor);
        if ($classe !== $x['classe']) {
            $texto = ['normal' => 'voltou ao normal', 'attention' => 'entrou em ATENÇÃO', 'critical' => 'entrou em CRÍTICO'][$classe];
            $this->log($s, $classe === 'normal' ? 'ok' : ($classe === 'attention' ? 'warn' : 'erro'),
                "{$codigo}: " . $this->fmt($valor, $x['casas']) . " {$x['unidade']} {$texto}.", $ts);
            $x['classe'] = $classe;
        }
        unset($x);
    }

    /* ========================================================== comandos */

    /**
     * Aplica um comando da tela.
     *   sensor: código do sensor, ou "todos" (só iniciar, pausar e reiniciar)
     *   acao:   iniciar | pausar | reiniciar | falha | modo | valor | config
     *
     * @param array<string,mixed> $c
     * @throws InvalidArgumentException comando ou valor inválido
     */
    public function comando(array $c): string
    {
        return $this->executar(function (array &$s) use ($c): string {
            $alvo = (string) ($c['sensor'] ?? '');
            $acao = (string) ($c['acao'] ?? '');

            if ($alvo === 'todos' && $acao === 'sincronizar') {
                $cadastro = self::lerCadastro();
                if ($cadastro === null) {
                    throw new InvalidArgumentException('O sistema não respondeu em ' . self::enderecoDoSistema() . '. Ligue o site (iniciar.bat) e tente de novo.');
                }
                $this->montarSensores($s, $cadastro);
                return 'Sensores sincronizados com o cadastro do sistema (' . count($s['sensores']) . ' sensores).';
            }

            if ($alvo === 'todos') {
                $mensagens =['iniciar' => 'Todos os sensores iniciados.', 'pausar' => 'Todos os sensores pausados.', 'reiniciar' => 'Todos os sensores voltaram aos valores iniciais.'];
                if (!isset($mensagens[$acao])) {
                    throw new InvalidArgumentException('Esta ação vale só para um sensor de cada vez.');
                }
                foreach (array_keys($s['sensores']) as $codigo) {
                    $this->aplicar($s, $codigo, $acao, $c, false);
                }
                $this->log($s, 'info', $mensagens[$acao]);
                return $mensagens[$acao];
            }

            if (!isset($s['sensores'][$alvo])) {
                throw new InvalidArgumentException('Sensor desconhecido.');
            }
            return $this->aplicar($s, $alvo, $acao, $c, true);
        });
    }

    private function aplicar(array &$s, string $codigo, string $acao, array $c, bool $registrar): string
    {
        $x = &$s['sensores'][$codigo];
        $un = $x['unidade'];

        switch ($acao) {
            case 'iniciar':
                if ($x['rodando']) {
                    return "{$codigo} já está em execução.";
                }
                $x['rodando'] = true;
                $msg = "{$codigo}: simulação iniciada.";
                $registrar && $this->log($s, 'info', $msg);
                if ($x['falha'] !== 'sem-resposta') {
                    unset($x);
                    $this->emitir($s, $codigo, time(), false);
                }
                return $msg;

            case 'pausar':
                $x['rodando'] = false;
                $msg = "{$codigo}: simulação pausada.";
                $registrar && $this->log($s, 'info', $msg);
                return $msg;

            case 'reiniciar':
                $x['valor'] = $x['inicial'];
                $x['modo'] = 'auto';
                $x['falha'] = '';
                $x['rodando'] = true;
                $x['hist'] = [];
                $x['sessao'] = self::sessaoVazia();
                $msg = "{$codigo}: reiniciado em " . $this->fmt($x['inicial'], $x['casas']) . " {$un}.";
                $registrar && $this->log($s, 'info', $msg);
                unset($x);
                $this->emitir($s, $codigo, time(), false);
                return $msg;

            case 'falha':
                // tipo: '' (corrigir), 'sem-resposta' ou 'valor-impossivel'. Sem tipo, alterna "sem resposta".
                $tipo = $c['tipo'] ?? ($x['falha'] === '' ? 'sem-resposta' : '');
                if (array_key_exists('ligar', $c)) {
                    $tipo = $c['ligar'] ? 'sem-resposta' : '';
                }
                if (!in_array($tipo, self::FALHAS, true)) {
                    throw new InvalidArgumentException('Tipo de falha inválido: use sem-resposta ou valor-impossivel.');
                }

                $antes = $x['falha'];
                $x['falha'] = $tipo;
                $x['emitidas'] = 0;
                $msg = [
                    ''                 => "{$codigo}: falha corrigida, o sensor voltou ao normal.",
                    'sem-resposta'     => "{$codigo}: FALHA simulada: o sensor parou de responder.",
                    'valor-impossivel' => "{$codigo}: FALHA simulada: a cada 3 leituras o sensor manda um valor impossível.",
                ][$tipo];
                $this->log($s, $tipo === '' ? 'ok' : 'erro', $msg);

                if ($antes === 'sem-resposta' && $tipo !== 'sem-resposta' && $x['rodando']) {
                    unset($x);
                    $this->emitir($s, $codigo, time(), false);           // voltou a medir
                }
                return $msg;

            case 'modo':
                if (!in_array($c['modo'] ?? '', ['auto', 'manual'], true)) {
                    throw new InvalidArgumentException('Modo inválido: use auto ou manual.');
                }
                $x['modo'] = $c['modo'];
                $msg = "{$codigo}: modo " . ($x['modo'] === 'auto' ? 'automático.' : 'manual.');
                $this->log($s, 'info', $msg);
                return $msg;

            case 'valor':
                $v = $c['valor'] ?? null;
                if (!is_numeric($v) || (float) $v < $x['abs_min'] || (float) $v > $x['abs_max']) {
                    throw new InvalidArgumentException('O valor precisa estar entre ' . $this->fmt($x['abs_min'], 0) . ' e ' . $this->fmt($x['abs_max'], 0) . " {$un}.");
                }
                $x['valor'] = round((float) $v, 3);
                $x['modo'] = 'manual';
                $msg = "{$codigo}: valor manual " . $this->fmt($x['valor'], $x['casas']) . " {$un}.";
                $this->log($s, 'info', $msg);
                if ($x['rodando'] && $x['falha'] !== 'sem-resposta') {
                    unset($x);
                    $this->emitir($s, $codigo, time(), false);
                }
                return $msg;

            case 'config':
                $var = $c['variacao'] ?? null;
                $int = $c['intervalo'] ?? null;
                $min = $c['faixa_min'] ?? $x['faixa_min'];
                $max = $c['faixa_max'] ?? $x['faixa_max'];

                if (!is_numeric($min) || !is_numeric($max) || (float) $min < $x['abs_min'] || (float) $max > $x['abs_max'] || (float) $min >= (float) $max) {
                    throw new InvalidArgumentException('A faixa precisa ficar entre ' . $this->fmt($x['abs_min'], 0) . ' e ' . $this->fmt($x['abs_max'], 0) . " {$un}, com o mínimo menor que o máximo.");
                }
                if (!is_numeric($var) || (float) $var < 0 || (float) $var > $x['var_max']) {
                    throw new InvalidArgumentException($x['rotulo_var'] . ': use um valor de 0 a ' . $this->fmt($x['var_max'], 2) . '.');
                }
                if (!is_numeric($int) || (int) $int < 1 || (int) $int > 300) {
                    throw new InvalidArgumentException('O intervalo precisa estar entre 1 e 300 segundos.');
                }
                $x['faixa_min'] = (float) $min;
                $x['faixa_max'] = (float) $max;
                $x['variacao'] = (float) $var;
                $x['intervalo'] = (int) $int;
                $x['valor'] = min($x['faixa_max'], max($x['faixa_min'], $x['valor']));
                $msg = "{$codigo}: configuração aplicada (intervalo " . (int) $int . ' s).';
                $this->log($s, 'info', $msg);
                return $msg;
        }

        throw new InvalidArgumentException('Comando desconhecido.');
    }

    /* ========================================================== estação */

    /**
     * Resposta à estação: leituras com número (seq) maior que $apos, mais a
     * situação de cada sensor. Com $codigo, devolve só aquele sensor e responde
     * 503 se ele estiver pausado ou em falha.
     *
     * @return array{http:int, corpo:array<string,mixed>}
     */
    public function consultar(int $apos, string $codigo = ''): array
    {
        return $this->executar(function (array &$s) use ($apos, $codigo): array {
            $s['consultas']++;
            $s['ultima_consulta'] = time();

            if ($codigo !== '' && !isset($s['sensores'][$codigo])) {
                return ['http' => 404, 'corpo' => ['ok' => false, 'erro' => 'Sensor desconhecido.']];
            }

            $estados = [];
            $atuais = [];
            foreach ($s['sensores'] as $c => $x) {
                if ($codigo === '' || $c === $codigo) {
                    // Com defeito, o sensor continua mandando leituras: para a estação ele está respondendo.
                    $d = $this->disponibilidade($x);
                    $estados[$c] = $d === 'defeito' ? 'ok' : $d;
                    $atuais[$c] = $x['ultima']['value'] ?? null;
                }
            }

            $leituras = [];
            foreach ($s['leituras'] as [$seq, $c, $valor, $ts]) {
                if ($seq > $apos && ($codigo === '' || $c === $codigo)) {
                    $leituras[] = ['seq' => $seq, 'code' => $c, 'value' => $valor, 'unit' => $s['sensores'][$c]['unidade'], 'recorded_at' => date(DATE_ATOM, $ts)];
                }
            }

            $corpo = ['ok' => true, 'execucao' => $s['execucao'], 'ultimo_seq' => $s['seq'], 'sensores' => $estados, 'atuais' => $atuais, 'leituras' => $leituras];

            if ($codigo !== '' && $estados[$codigo] !== 'ok') {
                $corpo['ok'] = false;
                $corpo['erro'] = $estados[$codigo] === 'falha' ? 'Sensor indisponível (falha simulada).' : 'Sensor pausado.';
                return ['http' => 503, 'corpo' => $corpo];
            }
            return ['http' => 200, 'corpo' => $corpo];
        });
    }

    /* ========================================================== tela */

    /** @return array<string,mixed> tudo o que a tela precisa; $codigo = sensor aberto em detalhe */
    public function visao(string $codigo): array
    {
        return $this->executar(function (array &$s) use ($codigo): array {
            $lista = [];
            $resumo = ['total' => 0, 'ok' => 0, 'pausado' => 0, 'falha' => 0, 'defeito' => 0];

            foreach ($s['sensores'] as $x) {
                $estado = $this->disponibilidade($x);
                $resumo['total']++;
                $resumo[$estado]++;
                $lista[] = $this->resumoDoSensor($x, $estado);
            }

            $detalhe = null;
            if (isset($s['sensores'][$codigo])) {
                $x = $s['sensores'][$codigo];
                $r = $x['sessao'];
                $detalhe = $this->resumoDoSensor($x, $this->disponibilidade($x)) + [
                    'config'    => ['faixa_min' => $x['faixa_min'], 'faixa_max' => $x['faixa_max'], 'variacao' => $x['variacao'], 'intervalo' => $x['intervalo']],
                    'abs_min'   => $x['abs_min'], 'abs_max' => $x['abs_max'], 'var_max' => $x['var_max'], 'rotulo_var' => $x['rotulo_var'],
                    'limites'   => $x['limites'], 'faixas' => $x['faixas'] ?? [], 'presets' => $x['presets'], 'inicial' => $x['inicial'],
                    'historico' => $x['hist'],
                    'sessao'    => ['n' => $r['n'], 'min' => $r['min'], 'max' => $r['max'], 'media' => $r['n'] > 0 ? round($r['soma'] / $r['n'], $x['casas']) : null],
                ];
            }

            return [
                'sensores'    => $lista,
                'detalhe'     => $detalhe,
                'resumo'      => $resumo,
                'comunicacao' => ['consultas' => $s['consultas'], 'ultima_consulta_ha' => $s['ultima_consulta'] > 0 ? time() - $s['ultima_consulta'] : null],
                'origem'      => ['tipo' => $s['origem'], 'sistema' => self::enderecoDoSistema()],
                'log'         => array_slice($s['log'], -20),
                'agora'       => time(),
            ];
        });
    }

    /* ========================================================== internos */

    private function resumoDoSensor(array $x, string $estado): array
    {
        $u = $x['ultima'];
        return [
            'code' => $x['codigo'], 'name' => $x['nome'], 'equip' => $x['equip'], 'type' => $x['tipo'],
            'unit' => $x['unidade'], 'casas' => $x['casas'], 'represa_id' => $x['represa_id'], 'represa' => $x['represa'],
            'estado' => $estado, 'modo' => $x['modo'], 'valor' => $x['valor'], 'falha' => $x['falha'],
            'leitura' => $u === null ? null : ['seq' => $u['seq'], 'value' => $u['value'], 'ts' => $u['ts'], 'classificacao' => self::classificar($x, (float) $u['value'])],
        ];
    }

    /** ok | pausado | falha (sem resposta) | defeito (mede, mas manda valores impossíveis de vez em quando) */
    private function disponibilidade(array $x): string
    {
        if ($x['falha'] === 'sem-resposta') {
            return 'falha';
        }
        if (!$x['rodando']) {
            return 'pausado';
        }
        return $x['falha'] === 'valor-impossivel' ? 'defeito' : 'ok';
    }

    private function log(array &$s, string $tipo, string $mensagem, ?int $ts = null): void
    {
        $s['log'][] = ['t' => date('H:i:s', $ts ?? time()), 'tipo' => $tipo, 'msg' => $mensagem];
        if (count($s['log']) > self::MAX_LOG) {
            $s['log'] = array_slice($s['log'], -self::MAX_LOG);
        }
    }

    /** @param float|int|string $n */
    private function fmt($n, int $casas = 1): string
    {
        return number_format((float) $n, $casas, ',', '.');
    }

    /** Número sem casas desnecessárias: 78.0 -> "78"; 68.4 -> "68,4". */
    private static function txt(float $n): string
    {
        return rtrim(rtrim(number_format($n, 2, ',', ''), '0'), ',');
    }
}
