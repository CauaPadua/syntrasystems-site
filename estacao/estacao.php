<?php
/**
 * Aquapulse — estação de telemetria (linha de comando).
 *
 * Faz o papel do equipamento que fica na barragem, entre os sensores e o
 * servidor. Liga uma estação para cada represa cadastrada; a cada ciclo, cada
 * uma lê os seus sensores, guarda as leituras na memória e envia tudo à API:
 *
 *   simulador de sensores  ->  ESTAÇÃO  ->  POST api/v1/ingest/batch.php  ->  banco  ->  painel
 *   (simulador-sensor/)        src/LeitorDeSensores.php, src/Estacao.php, src/Api.php
 *
 * O iniciar.bat já liga a estação em uma janela própria. Só pode haver uma
 * estação ligada por vez.
 *
 * USO (a partir da raiz do projeto, com o site rodando):
 *   php estacao/estacao.php                                    liga as estações de todas as represas
 *   php estacao/estacao.php --represa=santa-clara --pacote     uma estação, mostrando o JSON de cada envio
 *   php estacao/estacao.php --queda-rede                       sem sinal do ciclo 4 ao 11; depois envia o que guardou
 *   php estacao/estacao.php --equipamento=SEN-PH-01 --valor=15     envia um valor escolhido (teste da validação)
 *
 * OPÇÕES
 *   --url=URL           endereço do site                     (padrão: http://localhost:8000)
 *   --sensores=URL      endereço do simulador de sensores    (padrão: http://localhost:8100)
 *   --chave=CHAVE       chave de API das estações            (padrão: INGEST_API_KEY de backend/.env)
 *   --represa=ID        liga só a estação desta represa
 *   --equipamento=COD   só estes sensores (códigos separados por vírgula)
 *   --intervalo=SEG     segundos entre um ciclo e outro       (padrão: 5)
 *   --ciclos=N          para depois de N ciclos               (padrão: até Ctrl+C)
 *   --queda-rede        simula a perda do sinal da estação (ciclos 4 a 11)
 *   --pacote            mostra o JSON de cada pacote enviado
 *   --valor=X           envia uma única leitura com este valor e encerra
 *   --ajuda             mostra este texto
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
if (function_exists('sapi_windows_cp_set')) {
    sapi_windows_cp_set(65001);                                  // acentos corretos no terminal do Windows
}

require __DIR__ . '/src/LeitorDeSensores.php';
require __DIR__ . '/src/Estacao.php';
require __DIR__ . '/src/Api.php';

const SEM_SINAL = [4, 11];                                       // ciclos sem sinal com --queda-rede

/** Colunas da tabela no terminal: grandeza => [título, unidade, largura]. */
const COLUNAS = [
    'level'         => ['NÍVEL',   '%',    7],
    'inflow'        => ['ENTRADA', 'm³/s', 9],
    'outflow'       => ['SAÍDA',   'm³/s', 9],
    'ph'            => ['pH',      '',     6],
    'water_temp'    => ['ÁGUA',    '°C',   6],
    'precipitation' => ['CHUVA',   'mm',   7],
];

/* ================================================================ funções */

/** Lê as opções --nome=valor da linha de comando. */
function opcoes(array $argv): array
{
    $op = [
        'url' => 'http://localhost:8000', 'sensores' => 'http://localhost:8100', 'chave' => '', 'represa' => '',
        'equipamento' => '', 'intervalo' => '5', 'ciclos' => '0', 'queda-rede' => false, 'pacote' => false,
        'valor' => '', 'ajuda' => false,
    ];

    foreach (array_slice($argv, 1) as $argumento) {
        if (!preg_match('/^--([a-z-]+)(?:=(.*))?$/', $argumento, $m) || !array_key_exists($m[1], $op)) {
            parar("Opção desconhecida: {$argumento}. Use --ajuda para ver as opções.");
        }
        $op[$m[1]] = $m[2] ?? true;
    }
    return $op;
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

function parar(string $mensagem): void
{
    fwrite(STDERR, $mensagem . PHP_EOL);
    exit(1);
}

/** Texto alinhado em uma coluna de largura fixa (conta acentos como um caractere). */
function col(string $texto, int $largura, bool $direita = true): string
{
    $espaco = str_repeat(' ', max(0, $largura - mb_strlen($texto, 'UTF-8')));
    return $direita ? $espaco . $texto : $texto . $espaco;
}

function numero(float $valor, int $casas): string
{
    return number_format($valor, $casas, ',', '.');
}

/** "1 leitura gravada", "3 leituras gravadas". */
function qtd(int $n, string $singular, string $plural): string
{
    return $n . ' ' . ($n === 1 ? $singular : $plural);
}

function classificacao(string $c): string
{
    return ['normal' => 'normal', 'attention' => 'ATENÇÃO', 'critical' => 'CRÍTICO'][$c] ?? $c;
}

function cabecalho(): string
{
    $titulo = col('ESTAÇÃO', 12, false) . ' ' . col('REPRESA', 12, false);
    $unidade = str_repeat(' ', 25);
    foreach (COLUNAS as [$nome, $un, $largura]) {
        $titulo .= ' ' . col($nome, $largura);
        $unidade .= ' ' . col($un, $largura);
    }
    return $titulo . '   ENVIO' . PHP_EOL . $unidade . PHP_EOL;
}

/**
 * Uma linha da tabela: o último valor de cada sensor da estação.
 *
 * @param array<string,?float> $lidas
 */
function linhaDaEstacao(Estacao $estacao, array $lidas): string
{
    $porTipo = [];
    foreach ($estacao->equipamentos as $e) {
        $valor = $lidas[$e['id']] ?? null;
        $porTipo[$e['type']][] = $valor === null ? '---' : numero($valor, (int) $e['decimals']);   // --- = não respondeu
    }

    $linha = col($estacao->id, 12, false) . ' ' . col(preg_replace('/^Represa /', '', $estacao->represaNome), 12, false);
    foreach (COLUNAS as $tipo => [, , $largura]) {
        $linha .= ' ' . col(implode('/', $porTipo[$tipo] ?? []), $largura);
    }
    return $linha;
}

/** Resumo do envio, na coluna ENVIO. */
function textoDoEnvio(array $envio, int $lidasAgora, int $naMemoria): string
{
    $guardadas = qtd($naMemoria, 'leitura guardada', 'leituras guardadas') . ' na memória';
    if ($envio['situacao'] === 'sem-sinal') {
        return "SEM SINAL: {$guardadas}";
    }
    if ($envio['situacao'] === 'falha') {
        return "FALHA ({$envio['erro']}): {$guardadas}";
    }

    $texto = qtd(count($envio['gravadas']), 'gravada', 'gravadas');
    $enviadas = count($envio['gravadas']) + count($envio['recusadas']) + $envio['repetidas'];
    if ($enviadas > $lidasAgora) {
        $texto .= ' (' . ($enviadas - $lidasAgora) . ' estavam na memória)';
    }
    if ($envio['recusadas'] !== []) {
        $texto .= ', ' . qtd(count($envio['recusadas']), 'RECUSADA', 'RECUSADAS');
    }
    return $texto;
}

/* ================================================================= início */

$op = opcoes($argv);

if ($op['ajuda']) {
    preg_match('#/\*\*(.*?)\*/#s', (string) file_get_contents(__FILE__), $m);   // o comentário do topo é a ajuda
    echo preg_replace('/^\s*\* ?/m', '', trim($m[1])), PHP_EOL;
    exit(0);
}

$chave = chaveDeApi((string) $op['chave']);
$intervalo = (float) $op['intervalo'];
$limiteDeCiclos = (int) $op['ciclos'];

if ($chave === '') {
    parar('Chave de API não encontrada. Informe --chave=... ou defina INGEST_API_KEY em backend/.env.');
}
if ($intervalo < 1) {
    parar('O intervalo mínimo é 1 s (o banco guarda o horário com precisão de segundos).');
}

// Ctrl+C encerra no fim do ciclo, mostrando o resumo.
$encerrar = false;
if (function_exists('sapi_windows_set_ctrl_handler')) {
    sapi_windows_set_ctrl_handler(static function () use (&$encerrar): void { $encerrar = true; });
} elseif (function_exists('pcntl_signal')) {
    pcntl_async_signals(true);
    pcntl_signal(SIGINT, static function () use (&$encerrar): void { $encerrar = true; });
}

$api = new Api((string) $op['url'], $chave);
$fuso = new DateTimeZone('America/Sao_Paulo');

/* ------------------------- 1. a estação liga e pede o cadastro ao servidor */
$avisou = false;
while (true) {
    $resposta = $api->equipamentos((string) $op['represa']);
    if ($resposta['status'] === 200) {
        break;
    }
    if ($resposta['status'] !== 0) {
        parar("O servidor recusou a estação (HTTP {$resposta['status']}): " . ($resposta['body']['error']['message'] ?? 'erro desconhecido'));
    }
    if (!$avisou) {
        echo "Aguardando o servidor em {$op['url']} ... (o site precisa estar rodando: iniciar.bat)\n";
        $avisou = true;
    }
    for ($i = 0; $i < 30 && !$encerrar; $i++) {
        usleep(100000);
    }
    if ($encerrar) {
        exit(0);
    }
}

$cadastro = $resposta['body']['data']['equipment'] ?? [];
$porCodigo = array_column($cadastro, null, 'id');

/* --------------------------------- 2. envio de um único valor escolhido */
if ($op['valor'] !== '') {
    if ($op['equipamento'] === '') {
        parar('Informe o equipamento: --equipamento=SEN-PH-01 --valor=15');
    }

    $agora = new DateTimeImmutable('now', $fuso);
    $valor = (float) str_replace(',', '.', (string) $op['valor']);
    $codigos = array_map('strtoupper', array_map('trim', explode(',', (string) $op['equipamento'])));

    $leituras = [];
    foreach ($codigos as $codigo) {
        $leituras[] = ['equipment_id' => $codigo, 'recorded_at' => $agora->format(DATE_ATOM), 'value' => $valor];
    }

    $resposta = $api->enviar($leituras);
    if ($resposta['status'] !== 200) {
        parar("Falha no envio (HTTP {$resposta['status']}): " . ($resposta['body']['error']['message'] ?? 'o servidor não respondeu'));
    }

    foreach ($resposta['body']['data']['results'] as $r) {
        $e = $porCodigo[$codigos[$r['index']]] ?? null;
        $descricao = $e === null ? '(não cadastrado)' : $e['type_label'] . ' ' . numero($valor, (int) $e['decimals']) . ' ' . $e['unit'];
        $resultado = $r['ok']
            ? 'gravada [' . classificacao($r['classification']) . ']' . ($r['alert_id'] ? ' alerta ' . $r['alert_id'] : '')
            : "RECUSADA {$r['status']} {$r['code']}: {$r['message']}";
        echo $agora->format('H:i:s'), '  ', col($codigos[$r['index']], 11, false), ' ', $descricao, '  ->  ', $resultado, PHP_EOL;
    }
    exit(0);
}

/* ---------------------------------- 3. só uma estação ligada por vez */
// Duas estações lendo os mesmos sensores enviariam cada leitura duas vezes.
// A trava é solta sozinha quando o programa fecha.
$trava = fopen(__DIR__ . '/estacao.lock', 'c');
if ($trava === false || !flock($trava, LOCK_EX | LOCK_NB)) {
    parar('Já existe uma estação de telemetria ligada (janela "Estação de telemetria" aberta pelo iniciar.bat, ou outro terminal).'
        . PHP_EOL . 'Feche-a antes de ligar outra. Para só testar um valor, use --equipamento=... --valor=..., que funciona com ela ligada.');
}

/* --------------------------- 4. monta uma estação para cada represa */
$escolhidos = $op['equipamento'] === ''
    ? []
    : array_map('strtoupper', array_map('trim', explode(',', (string) $op['equipamento'])));

foreach (array_diff($escolhidos, array_keys($porCodigo)) as $codigo) {
    echo "{$codigo} não está cadastrado: ignorado\n";
}

$grupos = [];
$foraDeOperacao = [];
foreach ($cadastro as $e) {
    if ($escolhidos !== [] && !in_array($e['id'], $escolhidos, true)) {
        continue;
    }
    // Sem escolha explícita, só os ativos são lidos. Escolhido pelo código, o
    // equipamento é lido mesmo parado, para mostrar a API recusando.
    if ($escolhidos === [] && $e['status'] !== 'active') {
        $foraDeOperacao[] = $e;
        continue;
    }
    $grupos[$e['reservoir_id']][] = $e;
}

if ($grupos === []) {
    parar('Nenhum equipamento para ler.');
}

$estacoes = [];
foreach ($grupos as $represaId => $equipamentos) {
    $codigo = (string) ($equipamentos[0]['reservoir_code'] ?? '');
    $estacoes[] = new Estacao('EST-' . ($codigo !== '' ? $codigo : strtoupper($represaId)), (string) $equipamentos[0]['reservoir_name'],
        $equipamentos, new DateTimeImmutable('now', $fuso));
}

$leitor = new LeitorDeSensores((string) $op['sensores']);

/* ------------------------------------------------------------ apresentação */
$total = array_sum(array_map(static fn (Estacao $e): int => count($e->equipamentos), $estacoes));

echo PHP_EOL, 'AQUAPULSE · ESTAÇÕES DE TELEMETRIA', PHP_EOL;
echo "servidor {$op['url']} · sensores {$op['sensores']} · ciclo a cada ", numero($intervalo, 0), ' s · Ctrl+C encerra', PHP_EOL;
if ($op['queda-rede']) {
    echo 'simulação de queda de rede: sem sinal do ciclo ', SEM_SINAL[0], ' ao ', SEM_SINAL[1], '; a estação guarda tudo e envia quando o sinal volta', PHP_EOL;
}
echo PHP_EOL;

foreach ($estacoes as $estacao) {
    echo col($estacao->id, 12, false), ' ', col($estacao->represaNome, 21, false), ' ', count($estacao->equipamentos), ' sensores: ',
        implode(', ', array_column($estacao->equipamentos, 'id')), PHP_EOL;
}
foreach ($foraDeOperacao as $e) {
    echo '  ', $e['id'], ' não é lido: ', ['inactive' => 'desativado', 'maintenance' => 'em manutenção'][$e['status']] ?? $e['status'], ' no cadastro', PHP_EOL;
}
echo PHP_EOL, qtd(count($estacoes), 'estação', 'estações'), ', ', qtd($total, 'sensor', 'sensores'),
    '. A cada ciclo a estação busca as leituras novas no simulador de sensores e as envia ao servidor.', PHP_EOL;

/* ---------------------------------------------------------------- 5. ciclos */
$alertas = [];                                                   // alertas já mostrados: id => classificação
$avisosAnteriores = [];                                          // último aviso de "sem leitura" de cada estação
$contagem = ['gravadas' => 0, 'recusadas' => 0];
$ciclo = 0;

while (!$encerrar && ($limiteDeCiclos === 0 || $ciclo < $limiteDeCiclos)) {
    $ciclo++;
    $inicio = microtime(true);
    $agora = new DateTimeImmutable('now', $fuso);

    if (($ciclo - 1) % 12 === 0) {
        echo PHP_EOL, cabecalho();
    }

    $semSinal = $op['queda-rede'] && $ciclo >= SEM_SINAL[0] && $ciclo <= SEM_SINAL[1];
    echo "-- ciclo {$ciclo} · ", $agora->format('H:i:s'), $semSinal ? ' · SEM SINAL' : '', PHP_EOL;

    // A estação lê os sensores (mesmo sem sinal com o servidor: os sensores continuam ligados a ela).
    $leitor->atualizar();
    if ($leitor->mudanca === 'conectou') {
        echo '   >> simulador de sensores conectado: ', count($leitor->codigos()), ' sensores disponíveis', PHP_EOL;
    } elseif ($leitor->mudanca === 'caiu') {
        echo '   >> o simulador de sensores parou de responder: sem leituras novas até ele voltar', PHP_EOL;
    }
    if (!$leitor->conectado()) {
        echo "   simulador de sensores fora do ar em {$op['sensores']} (abra simulador-sensor\\abrir-sensor.bat): nenhuma leitura neste ciclo", PHP_EOL;
    }

    foreach ($estacoes as $estacao) {
        $lidas = $estacao->coletar($leitor);
        $estacao->comSinal = !$semSinal;
        $envio = $estacao->transmitir($api);

        echo linhaDaEstacao($estacao, $lidas), '   ', textoDoEnvio($envio, $estacao->lidasNoCiclo, $estacao->naMemoria()), PHP_EOL;

        // Sensores sem leitura: avisa quando a situação muda (e não a cada ciclo).
        if ($leitor->conectado() && $estacao->aviso !== ($avisosAnteriores[$estacao->id] ?? null)) {
            echo $estacao->aviso === null
                ? '     sensores voltaram a responder' . PHP_EOL
                : '     sem leitura: ' . $estacao->aviso . PHP_EOL;
            $avisosAnteriores[$estacao->id] = $estacao->aviso;
        }

        if ($op['pacote']) {
            foreach ($envio['pacotes'] as $pacote) {
                $json = json_encode(['readings' => $pacote], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                echo '     POST /api/v1/ingest/batch.php', PHP_EOL, preg_replace('/^/m', '     ', $json), PHP_EOL;
            }
        }

        foreach ($envio['recusadas'] as $r) {
            echo "     RECUSADA: {$r['equipment_id']} = ", numero((float) $r['value'], 2), " -> {$r['status']} {$r['code']}: {$r['message']}", PHP_EOL;
        }

        // Alertas de valor: aparecem quando surgem e quando a leitura passa de atenção para crítico.
        $porCodigoDaEstacao = array_column($estacao->equipamentos, null, 'id');
        foreach ($envio['gravadas'] as $g) {
            $antes = $g['alert_id'] === null ? null : ($alertas[$g['alert_id']] ?? null);
            if ($g['alert_id'] === null || ($antes !== null && ($antes === 'critical' || $g['classification'] !== 'critical'))) {
                continue;
            }
            $e = $porCodigoDaEstacao[$g['equipment_id']];
            $oQue = $e['type'] === 'precipitation'
                ? 'chuva acumulada em 24 h'
                : $e['type_label'] . ' ' . numero((float) $g['value'], (int) $e['decimals']) . ' ' . $e['unit'];

            echo '     ', $oQue, ' [', classificacao($g['classification']), "]  ->  alerta {$g['alert_id']}", PHP_EOL;
            $alertas[$g['alert_id']] = $g['classification'];
        }

        // Alertas de comunicação: o servidor percebeu um equipamento parado.
        foreach ($envio['comunicacao'] as $c) {
            echo "     ALERTA {$c['alert_id']}: {$c['equipment_id']} está sem enviar leituras há mais de 2 min (Equipamento sem leituras)", PHP_EOL;
        }

        $contagem['gravadas'] += count($envio['gravadas']);
        $contagem['recusadas'] += count($envio['recusadas']);
    }

    // Espera o restante do intervalo (ciclos regulares, como um datalogger).
    $fim = $inicio + $intervalo;
    while (!$encerrar && ($limiteDeCiclos === 0 || $ciclo < $limiteDeCiclos) && microtime(true) < $fim) {
        usleep(100000);
    }
}

/* ------------------------------------------------------------------ resumo */
$naMemoria = array_sum(array_map(static fn (Estacao $e): int => $e->naMemoria(), $estacoes));

echo PHP_EOL, 'Resumo: ', qtd($ciclo, 'ciclo', 'ciclos'), ', ', qtd($contagem['gravadas'], 'leitura gravada', 'leituras gravadas'),
    ', ', qtd($contagem['recusadas'], 'recusada', 'recusadas'), '.', PHP_EOL;
if ($naMemoria > 0) {
    echo $naMemoria === 1
        ? '1 leitura ficou na memória sem confirmação do servidor e não foi gravada.'
        : "{$naMemoria} leituras ficaram na memória sem confirmação do servidor e não foram gravadas.", PHP_EOL;
}
