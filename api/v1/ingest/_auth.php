<?php
/**
 * Aquapulse — autenticação dos endpoints de ingestão.
 *
 * Equipamentos não fazem login nem têm sessão: identificam-se por uma chave
 * enviada no cabeçalho X-Api-Key, que precisa ser igual à INGEST_API_KEY
 * configurada em backend/.env. Sem a chave configurada, a ingestão fica desligada.
 */

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/backend/bootstrap.php';

use Aquapulse\Http\Request;
use Aquapulse\Support\ApiResponse;
use Aquapulse\Support\Container;

/**
 * Valida método e chave, exige banco e carrega os limites em vigor.
 *
 * @return PDO conexão para o serviço de ingestão
 */
function aq_ingest_boot(string $method): PDO
{
    if (!Request::isMethod($method)) {
        ApiResponse::methodNotAllowed([$method]);
    }

    $chave = (string) getenv('INGEST_API_KEY');
    if ($chave === '') {
        ApiResponse::error('INGEST_DISABLED', 'A ingestão de leituras não está configurada neste ambiente.', 503);
    }

    $recebida = (string) ($_SERVER['HTTP_X_API_KEY'] ?? '');
    if (!hash_equals($chave, $recebida)) {                   // comparação em tempo constante
        ApiResponse::error('INVALID_API_KEY', 'Chave de API ausente ou inválida.', 401);
    }

    $pdo = Container::requireDatabase();
    Container::monitoring();                                 // carrega os limites de operational_limits em StatusRules

    return $pdo;
}
