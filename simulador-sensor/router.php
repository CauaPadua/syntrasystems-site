<?php
/**
 * Simulador de sensores — servidor local (porta 8100).
 *
 *   GET  /                                 a tela do simulador
 *   GET  /api/leituras?apos=N              leituras novas de todos os sensores (seq > N) + situação de cada um
 *   GET  /api/leituras?apos=N&sensor=COD   só um sensor; 503 se ele está pausado ou em falha
 *   GET  /api/estado?sensor=COD            o que a tela mostra (COD = sensor aberto em detalhe)
 *   POST /api/comando                      {"sensor": "SEN-NIV-01" | "todos", "acao": "...", ...}
 *
 * Roda com:  php -S localhost:8100 router.php   (ver abrir-sensor.bat)
 * Não acessa o banco de dados: quem conversa com o banco é a estação.
 */

declare(strict_types=1);

date_default_timezone_set('America/Sao_Paulo');
require __DIR__ . '/src/Sensores.php';

function responder(int $status, array $dados): bool
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($dados, JSON_UNESCAPED_UNICODE);
    return true;
}

$caminho = (string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$metodo = $_SERVER['REQUEST_METHOD'];
$sensores = new Sensores(__DIR__ . '/dados/estado.json');

if ($caminho === '/' || $caminho === '/index.html') {
    header('Content-Type: text/html; charset=utf-8');
    readfile(__DIR__ . '/public/index.html');
    return true;
}

if ($caminho === '/api/leituras' && $metodo === 'GET') {
    $r = $sensores->consultar((int) ($_GET['apos'] ?? 0), strtoupper(trim((string) ($_GET['sensor'] ?? ''))));
    return responder($r['http'], $r['corpo']);
}

if ($caminho === '/api/estado' && $metodo === 'GET') {
    return responder(200, $sensores->visao(strtoupper(trim((string) ($_GET['sensor'] ?? '')))));
}

if ($caminho === '/api/comando' && $metodo === 'POST') {
    $corpo = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($corpo)) {
        return responder(400, ['ok' => false, 'erro' => 'Envie um JSON.']);
    }
    try {
        return responder(200, ['ok' => true, 'mensagem' => $sensores->comando($corpo)]);
    } catch (InvalidArgumentException $e) {
        return responder(422, ['ok' => false, 'erro' => $e->getMessage()]);
    }
}

return responder(404, ['ok' => false, 'erro' => 'Não encontrado.']);
