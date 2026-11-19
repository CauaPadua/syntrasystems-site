<?php
/**
 * Aquapulse — o "modem" da estação: é por aqui que ela fala com o servidor.
 *
 * Usa só PHP puro (file_get_contents), como um equipamento simples faria:
 * HTTP com JSON e a chave da estação no cabeçalho X-Api-Key.
 */

declare(strict_types=1);

final class Api
{
    private string $base;
    private string $chave;

    public function __construct(string $url, string $chave)
    {
        $this->base = rtrim($url, '/') . '/api/v1/ingest';
        $this->chave = $chave;
    }

    /** Cadastro dos equipamentos (GET equipment.php): o que a estação deve ler e o último valor gravado. */
    public function equipamentos(string $represa = ''): array
    {
        $consulta = $represa !== '' ? '?reservoir_id=' . rawurlencode($represa) : '';
        return $this->pedir('GET', '/equipment.php' . $consulta);
    }

    /**
     * Envia um pacote de leituras (POST batch.php). $estacao identifica quem envia
     * (id, started_at, equipment): com ela, o servidor percebe sensores parados.
     *
     * @param list<array{equipment_id:string,recorded_at:string,value:float}> $leituras
     * @param array<string,mixed>|null $estacao
     */
    public function enviar(array $leituras, ?array $estacao = null): array
    {
        $corpo = ['readings' => $leituras];
        if ($estacao !== null) {
            $corpo['station'] = $estacao;
        }
        return $this->pedir('POST', '/batch.php', $corpo);
    }

    /**
     * @param array<string,mixed>|null $corpo
     * @return array{status:int,body:array<string,mixed>} status 0 = o servidor não respondeu
     */
    private function pedir(string $metodo, string $caminho, ?array $corpo = null): array
    {
        $cabecalhos = ['Accept: application/json', 'X-Api-Key: ' . $this->chave];
        $http = ['method' => $metodo, 'timeout' => 15, 'ignore_errors' => true];   // ignore_errors: lê o corpo também em 4xx/5xx

        if ($corpo !== null) {
            $cabecalhos[] = 'Content-Type: application/json';
            $http['content'] = json_encode($corpo);
        }
        $http['header'] = implode("\r\n", $cabecalhos);

        $resposta = @file_get_contents($this->base . $caminho, false, stream_context_create(['http' => $http]));

        if ($resposta === false) {
            return ['status' => 0, 'body' => []];
        }

        // A primeira linha dos cabeçalhos é do tipo "HTTP/1.1 200 OK".
        $status = 0;
        if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0] . ' ', $m)) {
            $status = (int) $m[1];
        }

        $json = json_decode($resposta, true);
        return ['status' => $status, 'body' => is_array($json) ? $json : []];
    }
}
