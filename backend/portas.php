<?php
/**
 * Aquapulse — escolhe as portas antes de ligar o projeto (usado pelo iniciar.bat).
 *
 * Para o site (porta 8000) e o simulador de sensores (porta 8100):
 *   porta livre                       -> liga nela;
 *   ocupada pelo próprio Aquapulse    -> já está ligado: não liga outro, só abre a tela;
 *   ocupada por outro programa        -> usa a próxima porta livre (8001, 8002... / 8101...).
 * Nada que já está rodando no computador é encerrado.
 *
 * Também confere se a estação de telemetria já está ligada (trava em estacao/estacao.lock).
 *
 * O resultado vai para um .bat com "set", que o iniciar.bat executa com "call":
 *   PORT, SENSOR_PORT, LIGAR_SITE, LIGAR_SENSORES, LIGAR_ESTACAO   (1 = ligar, 0 = já está ligado)
 *
 * USO:  php backend/portas.php SAIDA.bat             site, sensores e estação (iniciar.bat)
 *       php backend/portas.php SAIDA.bat sensores    só o simulador (abrir-sensor.bat sozinho)
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
if (function_exists('sapi_windows_cp_set')) {
    sapi_windows_cp_set(65001);                                   // acentos corretos na janela
}

const TENTATIVAS = 30;                                            // procura até a porta preferida + 30

$saida = $argv[1] ?? '';
$soSensores = ($argv[2] ?? '') === 'sensores';
if ($saida === '') {
    fwrite(STDERR, "uso: php backend/portas.php SAIDA.bat [sensores]\n");
    exit(1);
}
$raiz = dirname(__DIR__);

/**
 * A porta está livre para o nosso servidor? Não depende do idioma do Windows,
 * ao contrário do netstat ("LISTENING" / "ESCUTANDO").
 *   1. tenta conectar: se alguém atende, está ocupada (no Windows, só tentar
 *      abrir a porta não basta: ele deixa abrir uma porta que já está em uso);
 *   2. tenta abrir a porta: falha nas portas que o Windows reserva (Hyper-V,
 *      WSL, Docker), onde ninguém atende mas o servidor também não conseguiria ligar.
 */
function livre(int $porta): bool
{
    $conexao = @fsockopen('localhost', $porta, $erro, $texto, 0.3);   // local atende em milissegundos
    if ($conexao !== false) {
        fclose($conexao);
        return false;
    }

    $ctx = stream_context_create(['socket' => ['so_reuseaddr' => false]]);
    foreach (['127.0.0.1', '[::1]'] as $endereco) {
        $socket = @stream_socket_server("tcp://{$endereco}:{$porta}", $erro, $texto, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN, $ctx);
        if ($socket !== false) {
            fclose($socket);
            continue;
        }
        if (in_array($erro, [10047, 10049, 97, 99], true)) {
            continue;                                             // o computador não tem IPv6 (ou IPv4): não conta
        }
        return false;                                             // em uso (ou reservada pelo Windows)
    }
    return true;
}

/** Nome do programa que está escutando na porta (para a mensagem), pelo netstat. */
function quemUsa(int $porta): string
{
    $texto = (string) shell_exec('netstat -ano');
    // Linha de escuta: endereço remoto 0.0.0.0:0 ou [::]:0 (não depende do idioma de "LISTENING").
    if (!preg_match('/^\s*TCP\s+\S+:' . $porta . '\s+(?:0\.0\.0\.0|\[::\]):0\s+\S+\s+(\d+)/mi', $texto, $m)) {
        return 'outro programa';
    }
    $linha = trim((string) shell_exec('tasklist /FI "PID eq ' . (int) $m[1] . '" /FO CSV /NH'));
    $nome = preg_match('/^"([^"]+)"/', $linha, $n) ? $n[1] : 'programa';
    return "{$nome} (PID {$m[1]})";
}

/** Faz um GET em http://localhost:PORTA/caminho e devolve o corpo, ou null. */
function pedir(int $porta, string $caminho): ?string
{
    $ctx = stream_context_create(['http' => ['timeout' => 3, 'ignore_errors' => true]]);
    $corpo = @file_get_contents("http://localhost:{$porta}{$caminho}", false, $ctx);
    return $corpo === false ? null : $corpo;
}

function ehNossoSite(int $porta): bool
{
    $corpo = pedir($porta, '/login.php');
    return $corpo !== null && stripos($corpo, 'Aquapulse') !== false;
}

function ehNossoSimulador(int $porta): bool
{
    $json = json_decode((string) pedir($porta, '/api/estado'), true);
    return is_array($json) && isset($json['sensores'], $json['comunicacao']);
}

/**
 * Decide a porta de um serviço.
 *
 * @return array{0:int,1:bool} [porta, ligar]   ligar=false: o Aquapulse já está nela
 */
function escolher(string $nome, int $preferida, callable $ehNosso): array
{
    if (livre($preferida)) {
        echo "  {$nome}: porta {$preferida} livre.\n";
        return [$preferida, true];
    }
    if ($ehNosso($preferida)) {
        echo "  {$nome}: já está ligado na porta {$preferida} (janela aberta antes). Vou só abrir a tela.\n";
        return [$preferida, false];
    }

    $ocupante = quemUsa($preferida);
    for ($porta = $preferida + 1; $porta <= $preferida + TENTATIVAS; $porta++) {
        if (livre($porta)) {
            echo "  {$nome}: a porta {$preferida} está ocupada por {$ocupante}. Vou usar a porta {$porta}.\n";
            return [$porta, true];
        }
        if ($ehNosso($porta)) {
            echo "  {$nome}: já está ligado na porta {$porta}. Vou só abrir a tela.\n";
            return [$porta, false];
        }
    }
    fwrite(STDERR, "  {$nome}: não encontrei porta livre entre {$preferida} e " . ($preferida + TENTATIVAS) . ".\n");
    exit(1);
}

/* ------------------------------------------------------------------ início */
$linhas = [];
echo "Verificando as portas...\n";

if (!$soSensores) {
    [$portaSite, $ligarSite] = escolher('Site', (int) (getenv('PORT') ?: 8000), 'ehNossoSite');
    $linhas[] = "set PORT={$portaSite}";
    $linhas[] = 'set LIGAR_SITE=' . ($ligarSite ? 1 : 0);
}

[$portaSensores, $ligarSensores] = escolher('Simulador de sensores', (int) (getenv('SENSOR_PORT') ?: 8100), 'ehNossoSimulador');
$linhas[] = "set SENSOR_PORT={$portaSensores}";
$linhas[] = 'set LIGAR_SENSORES=' . ($ligarSensores ? 1 : 0);

if (!$soSensores) {
    // A estação segura a trava enquanto está ligada. Se der para pegar a trava, ela não está.
    $trava = @fopen("{$raiz}/estacao/estacao.lock", 'c');
    $estacaoLigada = $trava !== false && !flock($trava, LOCK_EX | LOCK_NB);
    if ($trava !== false) {
        if (!$estacaoLigada) {
            flock($trava, LOCK_UN);
        }
        fclose($trava);
    }

    $ligarEstacao = true;
    if ($estacaoLigada) {
        $ligarEstacao = false;
        $info = json_decode((string) @file_get_contents("{$raiz}/estacao/estacao.info"), true);
        $esperado = ['url' => "http://localhost:{$portaSite}", 'sensores' => "http://localhost:{$portaSensores}"];
        if (is_array($info) && ($info['url'] ?? '') === $esperado['url'] && ($info['sensores'] ?? '') === $esperado['sensores']) {
            echo "  Estação: já está ligada (janela \"Estação de telemetria\").\n";
        } else {
            echo "  ATENÇÃO: já existe uma janela \"Estação de telemetria\" aberta, ligada a outras portas.\n";
            echo "           Feche essa janela e rode o iniciar.bat de novo.\n";
        }
    }
    $linhas[] = 'set LIGAR_ESTACAO=' . ($ligarEstacao ? 1 : 0);
}

file_put_contents($saida, implode("\r\n", $linhas) . "\r\n");
