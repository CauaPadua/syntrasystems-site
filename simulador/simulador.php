<?php
/**
 * Aquapulse — simulador de equipamentos (linha de comando).
 *
 * Faz o papel de um equipamento de campo: gera medições fictícias e as envia
 * para a API, que valida e grava no banco. O painel passa a mostrar essas
 * leituras.
 *
 *     simulador  ->  POST api/v1/ingest/readings.php  ->  banco (readings)  ->  painel
 *
 * USO (a partir da raiz do projeto, com o site rodando):
 *   php simulador/simulador.php                          todos os equipamentos ativos
 *   php simulador/simulador.php --represa=santa-clara    só os de uma represa
 *   php simulador/simulador.php --equipamento=SEN-NIV-01 só um equipamento
 *   php simulador/simulador.php --cenario=alerta         leva os valores para a faixa de alerta
 *   php simulador/simulador.php --equipamento=SEN-PH-01 --valor=15   envia um valor específico (teste da validação)
 *
 * OPÇÕES
 *   --url=URL          endereço do site             (padrão: http://localhost:8000)
 *   --chave=CHAVE      chave de API dos equipamentos (padrão: INGEST_API_KEY de backend/.env)
 *   --represa=ID       equipamentos ativos de uma represa
 *   --equipamento=COD  um ou mais códigos, separados por vírgula
 *   --cenario=NOME     normal (padrão) ou alerta
 *   --intervalo=SEG    segundos entre os envios      (padrão: 5)
 *   --envios=N         para depois de N rodadas      (padrão: roda até Ctrl+C)
 *   --valor=X          envia uma única leitura com este valor e encerra
 *   --ajuda            mostra este texto
 *
 * COMO OS VALORES SÃO GERADOS
 * Cada equipamento parte do último valor registrado para a sua grandeza e, a
 * cada rodada, dá um passo pequeno: uma parte em direção a um valor-alvo e uma
 * parte aleatória. Isso produz curvas contínuas, sem saltos. No cenário
 * "normal" o alvo é o próprio valor de partida; no cenário "alerta" o alvo fica
 * além do limite cadastrado, então a leitura cruza a faixa de atenção e depois
 * a crítica — de forma acelerada, para caber em uma demonstração.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

/* ============================================================= configuração */

/** Lê as opções --nome=valor da linha de comando. */
function opcoes(array $argv): array
{
    $padrao = [
        'url'         => 'http://localhost:8000',
        'chave'       => '',
        'represa'     => '',
        'equipamento' => '',
        'cenario'     => 'normal',
        'intervalo'   => '5',
        'envios'      => '0',
        'valor'       => '',
        'ajuda'       => false,
    ];

    foreach (array_slice($argv, 1) as $argumento) {
        if (!preg_match('/^--([a-z]+)(?:=(.*))?$/', $argumento, $m) || !array_key_exists($m[1], $padrao)) {
            fwrite(STDERR, "Opção desconhecida: {$argumento}\nUse --ajuda para ver as opções.\n");
            exit(1);
        }
        $padrao[$m[1]] = $m[2] ?? true;
    }

    return $padrao;
}

/** Chave de API: a informada, a variável de ambiente ou a de backend/.env. */
function chaveDeApi(string $informada): string
{
    if ($informada !== '') {
        return $informada;
    }
    if (getenv('INGEST_API_KEY')) {
        return (string) getenv('INGEST_API_KEY');
    }

    $env = dirname(__DIR__) . '/backend/.env';
    if (is_file($env) && preg_match('/^INGEST_API_KEY=(.+)$/m', (string) file_get_contents($env), $m)) {
        return trim($m[1]);
    }

    return '';
}

/* ===================================================================== HTTP */

/**
 * Faz uma requisição à API e devolve o status HTTP e o corpo decodificado.
 *
 * @return array{status:int,body:array<string,mixed>}
 */
function requisitar(string $metodo, string $url, string $chave, ?array $corpo = null): array
{
    $cabecalhos = ['Accept: application/json', 'X-Api-Key: ' . $chave];
    $http = ['method' => $metodo, 'timeout' => 10, 'ignore_errors' => true];   // ignore_errors: lê o corpo também em 4xx/5xx

    if ($corpo !== null) {
        $cabecalhos[] = 'Content-Type: application/json';
        $http['content'] = json_encode($corpo);
    }
    $http['header'] = implode("\r\n", $cabecalhos);

    $resposta = @file_get_contents($url, false, stream_context_create(['http' => $http]));

    if ($resposta === false) {
        return ['status' => 0, 'body' => []];                 // servidor fora do ar ou endereço errado
    }

    // A primeira linha dos cabeçalhos é do tipo "HTTP/1.1 201 Created".
    $status = 0;
    if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0] . ' ', $m)) {
        $status = (int) $m[1];
    }

    $json = json_decode($resposta, true);
    return ['status' => $status, 'body' => is_array($json) ? $json : []];
}

/* ================================================================ simulação */

/** Número aleatório com distribuição normal (média 0, desvio 1) — método de Box-Muller. */
function ruido(): float
{
    $u1 = max(mt_rand() / mt_getrandmax(), 1e-9);
    $u2 = mt_rand() / mt_getrandmax();
    return sqrt(-2 * log($u1)) * cos(2 * M_PI * $u2);
}

/**
 * Estado inicial de um equipamento: de onde parte e para onde tende.
 *
 * @param array<string,mixed> $e equipamento devolvido pela API
 * @return array{valor:float,alvo:float,passo:float,oscilacao:float}
 */
function estadoInicial(array $e, string $cenario): array
{
    $partidaPadrao = ['level' => 70.0, 'inflow' => 40.0, 'outflow' => 35.0, 'ph' => 7.2, 'precipitation' => 0.0, 'water_temp' => 22.0];
    $valor = $e['current_value'] ?? $partidaPadrao[$e['type']];

    // oscilacao = tamanho do componente aleatório de cada passo, na unidade da grandeza
    $oscilacao = ['level' => 0.04, 'inflow' => 0.35, 'outflow' => 0.30, 'ph' => 0.015, 'precipitation' => 0.0, 'water_temp' => 0.04][$e['type']];

    $alvo = (float) $valor;                                   // cenário normal: oscila em torno do valor de partida
    $passo = 0.05;                                            // fração da distância até o alvo percorrida por rodada

    if ($cenario === 'alerta') {
        $limites = $e['limits'];
        $passo = 0.08;

        if (isset($limites['critical']['min'])) {             // grandeza com limite superior (nível)
            $alvo = (float) $limites['critical']['min'] + 0.02 * ($e['max'] - $e['min']);
        } elseif (isset($limites['normal']['min'])) {         // grandeza com faixa ideal (pH): sai por baixo
            $alvo = (float) $limites['normal']['min'] - 0.5;
        } elseif ($e['type'] === 'inflow') {                  // cheia: entra bem mais água...
            $alvo = $valor * 1.35;
        } elseif ($e['type'] === 'outflow') {                 // ...e a saída aumenta menos
            $alvo = $valor * 1.10;
        }
    }

    return ['valor' => (float) $valor, 'alvo' => (float) $alvo, 'passo' => $passo, 'oscilacao' => $oscilacao];
}

/**
 * Próxima leitura de um equipamento.
 *
 * @param array<string,mixed> $e      equipamento
 * @param array<string,float> $estado estado atual (alterado por referência)
 */
function proximaLeitura(array $e, array &$estado, string $cenario, float $intervalo): float
{
    if ($e['type'] === 'precipitation') {
        // A leitura de chuva é o quanto choveu desde o envio anterior (mm).
        if ($cenario === 'alerta') {
            $mm = 1.0 + 1.5 * (mt_rand() / mt_getrandmax());              // temporal: 1,0 a 2,5 mm por envio
        } else {
            $chove = (mt_rand() / mt_getrandmax()) < 0.004 * ($intervalo / 5);   // pancadas ocasionais e fracas
            $mm = $chove ? 0.1 + 0.2 * (mt_rand() / mt_getrandmax()) : 0.0;
        }
        return round($mm, (int) $e['decimals']);
    }

    // Passo em direção ao alvo + variação aleatória.
    $estado['valor'] += ($estado['alvo'] - $estado['valor']) * $estado['passo'] + ruido() * $estado['oscilacao'];
    $estado['valor'] = min((float) $e['max'], max((float) $e['min'], $estado['valor']));   // nunca sai da faixa física

    return round($estado['valor'], (int) $e['decimals']);
}

/** Uma linha do relatório no terminal. */
function linha(string $hora, array $e, float $valor, array $resposta): string
{
    $texto = sprintf('%s  %-11s %-22s %9s %-5s', $hora, $e['id'], $e['type_label'], number_format($valor, (int) $e['decimals'], ',', '.'), $e['unit']);

    if ($resposta['status'] === 0) {
        return $texto . ' SEM RESPOSTA do servidor';
    }

    if ($resposta['status'] === 201) {
        $leitura = $resposta['body']['data']['reading'] ?? [];
        $faixa = ['normal' => 'normal', 'attention' => 'ATENÇÃO', 'critical' => 'CRÍTICO'][$leitura['classification'] ?? 'normal'];
        $alerta = empty($leitura['alert_id']) ? '' : '  alerta ' . $leitura['alert_id'];
        return $texto . " gravado  [{$faixa}]{$alerta}";
    }

    $erro = $resposta['body']['error'] ?? [];
    return $texto . sprintf(' RECUSADO %d %s: %s', $resposta['status'], $erro['code'] ?? '', $erro['message'] ?? '');
}

/* =================================================================== início */

$op = opcoes($argv);

if ($op['ajuda']) {
    // O próprio comentário do topo do arquivo é o texto de ajuda.
    preg_match('#/\*\*(.*?)\*/#s', (string) file_get_contents(__FILE__), $m);
    echo preg_replace('/^\s*\* ?/m', '', trim($m[1])), PHP_EOL;
    exit(0);
}

$base = rtrim((string) $op['url'], '/') . '/api/v1/ingest';
$chave = chaveDeApi((string) $op['chave']);
$cenario = (string) $op['cenario'];
$intervalo = max(1.0, (float) $op['intervalo']);              // menos de 1 s geraria leituras com o mesmo horário
$limiteDeEnvios = (int) $op['envios'];

if ($chave === '') {
    fwrite(STDERR, "Chave de API não encontrada. Informe --chave=... ou defina INGEST_API_KEY em backend/.env.\n");
    exit(1);
}
if (!in_array($cenario, ['normal', 'alerta'], true)) {
    fwrite(STDERR, "Cenário inválido: use --cenario=normal ou --cenario=alerta.\n");
    exit(1);
}

/* ------------------------------- 1. pergunta à API o que há para simular */
$consulta = $op['represa'] !== '' ? '?reservoir_id=' . rawurlencode((string) $op['represa']) : '';
$resposta = requisitar('GET', $base . '/equipment.php' . $consulta, $chave);

if ($resposta['status'] !== 200) {
    $erro = $resposta['body']['error']['message'] ?? 'verifique se o site está rodando em ' . $op['url'];
    fwrite(STDERR, "Não foi possível consultar os equipamentos (HTTP {$resposta['status']}): {$erro}\n");
    exit(1);
}

$equipamentos = $resposta['body']['data']['equipment'];

if ($op['equipamento'] !== '') {
    // Equipamento escolhido pelo código: é enviado mesmo que esteja desativado,
    // para mostrar a API recusando a leitura.
    $codigos = array_map('strtoupper', array_map('trim', explode(',', (string) $op['equipamento'])));
    $equipamentos = array_values(array_filter($equipamentos, static fn (array $e): bool => in_array($e['id'], $codigos, true)));

    foreach (array_diff($codigos, array_column($equipamentos, 'id')) as $faltando) {
        // Código que não está no cadastro: simulado como nível, só para a API responder "não cadastrado".
        $equipamentos[] = ['id' => $faltando, 'type' => 'level', 'type_label' => '(não cadastrado)', 'unit' => '', 'min' => 0, 'max' => 100,
                           'decimals' => 1, 'status' => 'active', 'reservoir_name' => '—', 'current_value' => null, 'limits' => []];
    }
} else {
    $fora = array_filter($equipamentos, static fn (array $e): bool => $e['status'] !== 'active');
    foreach ($fora as $e) {
        echo "ignorado: {$e['id']} não está ativo no cadastro\n";
    }
    $equipamentos = array_values(array_filter($equipamentos, static fn (array $e): bool => $e['status'] === 'active'));
}

if ($equipamentos === []) {
    fwrite(STDERR, "Nenhum equipamento para simular.\n");
    exit(1);
}

$fuso = new DateTimeZone('America/Sao_Paulo');

/* -------------------------------------- 2. envio único de um valor fixo */
if ($op['valor'] !== '') {
    $agora = new DateTimeImmutable('now', $fuso);
    foreach ($equipamentos as $e) {
        $valor = (float) str_replace(',', '.', (string) $op['valor']);
        $r = requisitar('POST', $base . '/readings.php', $chave, [
            'equipment_id' => $e['id'],
            'recorded_at'  => $agora->format(DATE_ATOM),
            'value'        => $valor,
        ]);
        echo linha($agora->format('H:i:s'), $e, $valor, $r), PHP_EOL;
    }
    exit(0);
}

/* ------------------------------------------- 3. simulação contínua */
echo 'Simulando ', count($equipamentos), " equipamento(s) | cenário: {$cenario} | um envio a cada {$intervalo} s | Ctrl+C para parar\n";
echo str_repeat('-', 96), PHP_EOL;

$estados = [];
foreach ($equipamentos as $e) {
    $estados[$e['id']] = estadoInicial($e, $cenario);
}

$rodada = 0;
$gravadas = 0;
$recusadas = 0;

while ($limiteDeEnvios === 0 || $rodada < $limiteDeEnvios) {
    $rodada++;
    $agora = new DateTimeImmutable('now', $fuso);             // todos os equipamentos da rodada medem no mesmo instante

    foreach ($equipamentos as $e) {
        $valor = proximaLeitura($e, $estados[$e['id']], $cenario, $intervalo);

        $r = requisitar('POST', $base . '/readings.php', $chave, [
            'equipment_id' => $e['id'],
            'recorded_at'  => $agora->format(DATE_ATOM),
            'value'        => $valor,
        ]);

        $r['status'] === 201 ? $gravadas++ : $recusadas++;
        echo linha($agora->format('H:i:s'), $e, $valor, $r), PHP_EOL;
    }

    if ($limiteDeEnvios === 0 || $rodada < $limiteDeEnvios) {
        usleep((int) ($intervalo * 1000000));
    }
}

echo str_repeat('-', 96), PHP_EOL;
echo "{$rodada} rodada(s): {$gravadas} leitura(s) gravada(s), {$recusadas} recusada(s).\n";
