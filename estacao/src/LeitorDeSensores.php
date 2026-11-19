<?php
/**
 * Aquapulse — a ligação da estação com os sensores.
 *
 * Em campo, os sensores ficam ligados à estação por cabo. Aqui, os sensores são
 * o simulador de sensores (simulador-sensor/), um programa à parte, e este
 * leitor faz o papel do cabo: uma vez por ciclo pergunta quais leituras novas
 * existem.
 *
 *   GET {url}/api/leituras?apos=N   ->  leituras novas de todos os sensores + situação de cada um
 *
 * O leitor guarda o número da última leitura recebida (cursor) e pede só as
 * seguintes. Se o simulador sair do ar, a estação fica sem leituras novas e o
 * leitor tenta de novo a cada ciclo; quando ele volta, continua de onde parou.
 * Se o simulador for fechado e aberto de novo (a numeração recomeça), percebe
 * pela "execucao" e recomeça a contagem.
 */

declare(strict_types=1);

final class LeitorDeSensores
{
    public string $url;
    /** O que mudou na última atualização: 'conectou', 'caiu' ou null. */
    public ?string $mudanca = null;

    private bool $conectado = false;
    private int $cursor = 0;
    private string $execucao = '';
    /** @var array<string,string> situação de cada sensor: ok | pausado | falha */
    private array $estados = [];
    /** @var array<string,?float> último valor de cada sensor */
    private array $atuais = [];
    /** @var array<string,list<array{value:float,recorded_at:string}>> leituras recebidas e ainda não retiradas */
    private array $pendentes = [];

    public function __construct(string $url)
    {
        $this->url = rtrim($url, '/');
    }

    public function conectado(): bool
    {
        return $this->conectado;
    }

    /** @return list<string> sensores existentes no simulador */
    public function codigos(): array
    {
        return array_keys($this->estados);
    }

    /** Uma consulta por ciclo: busca as leituras novas e as separa por equipamento. */
    public function atualizar(): void
    {
        $this->mudanca = null;

        if (!$this->conectado) {
            if (!$this->conectar()) {
                return;
            }
            $this->mudanca = 'conectou';
        }

        $r = $this->pedir('/api/leituras?apos=' . $this->cursor);

        // Simulador fechado e aberto de novo: a numeração recomeçou do zero.
        if ($r['status'] === 200 && (string) ($r['body']['execucao'] ?? '') !== $this->execucao) {
            $this->execucao = (string) ($r['body']['execucao'] ?? '');
            $this->cursor = 0;
            $r = $this->pedir('/api/leituras?apos=0');
        }

        if ($r['status'] !== 200) {
            $this->conectado = false;
            $this->mudanca = 'caiu';
            return;
        }

        $this->estados = $r['body']['sensores'] ?? [];
        $this->atuais = $r['body']['atuais'] ?? [];
        foreach ($r['body']['leituras'] ?? [] as $l) {
            $this->pendentes[(string) $l['code']][] = ['value' => (float) $l['value'], 'recorded_at' => (string) $l['recorded_at']];
            $this->cursor = max($this->cursor, (int) $l['seq']);
        }
    }

    /** @return list<array{value:float,recorded_at:string}> leituras novas do equipamento (saem da fila) */
    public function retirar(string $codigo): array
    {
        $lista = $this->pendentes[$codigo] ?? [];
        unset($this->pendentes[$codigo]);
        return $lista;
    }

    /** ok | pausado | falha | ausente (não existe no simulador) | sem-resposta (simulador fora do ar) */
    public function estado(string $codigo): string
    {
        if (!$this->conectado) {
            return 'sem-resposta';
        }
        return $this->estados[$codigo] ?? 'ausente';
    }

    public function atual(string $codigo): ?float
    {
        $v = $this->atuais[$codigo] ?? null;
        return $v === null ? null : (float) $v;
    }

    /**
     * Encontra o simulador. Na primeira vez, começa pelas leituras geradas daqui
     * para frente. Se a conexão só tinha caído, continua do cursor, sem perder
     * as leituras geradas enquanto isso.
     */
    private function conectar(): bool
    {
        $r = $this->pedir('/api/leituras?apos=2147483647');
        if ($r['status'] !== 200 || !isset($r['body']['sensores'])) {
            return false;
        }

        $execucao = (string) ($r['body']['execucao'] ?? '');
        if ($this->execucao === '') {
            $this->cursor = (int) ($r['body']['ultimo_seq'] ?? 0);     // primeira conexão
        } elseif ($execucao !== $this->execucao) {
            $this->cursor = 0;                                         // simulador foi religado
        }
        $this->execucao = $execucao;
        $this->estados = $r['body']['sensores'];
        $this->atuais = $r['body']['atuais'] ?? [];
        $this->conectado = true;
        return true;
    }

    /** @return array{status:int,body:array<string,mixed>} status 0 = o simulador não respondeu */
    private function pedir(string $caminho): array
    {
        $ctx = stream_context_create(['http' => ['method' => 'GET', 'timeout' => 3, 'ignore_errors' => true, 'header' => 'Accept: application/json']]);
        $resposta = @file_get_contents($this->url . $caminho, false, $ctx);

        if ($resposta === false) {
            return ['status' => 0, 'body' => []];
        }

        $status = 0;
        if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0] . ' ', $m)) {
            $status = (int) $m[1];
        }
        $json = json_decode($resposta, true);
        return ['status' => $status, 'body' => is_array($json) ? $json : []];
    }
}
