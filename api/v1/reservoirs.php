<?php
/** GET /api/v1/reservoirs.php?company_id={id|all} — represas da empresa. */
/*
 * Chamado por: AqApi.reservoirs() — preenche os seletores de represa.
 * Entrada: company_id opcional (ausente ou 'all' = todas as empresas).
 * Erros: 404 INVALID_COMPANY se a empresa informada não existir.
 * Devolve uma versão resumida de cada represa (só o necessário para listas).
 */

declare(strict_types=1);

require_once __DIR__ . '/_boot.php';

use Aquapulse\Services\StatusRules;
use Aquapulse\Support\ApiResponse;
use Aquapulse\Support\Validator;

[$repo] = aq_api_boot();

$companyId = Validator::companyId($repo->companies());                   // valida ?company_id contra as empresas existentes

$list = array_map(static function (array $r): array {                   // transforma cada represa em um item resumido
    $status = StatusRules::fromLevel((float) $r['level_pct']);           // status calculado pelo nível (mesma regra do sistema todo)
    return [
        'id'         => $r['id'],
        'code'       => $r['code'],
        'name'       => $r['name'],
        'company_id' => $r['company_id'],
        'city'       => $r['city'],
        'level'      => $r['level_pct'],
        'status'     => StatusRules::describe($status),                  // {key, label, icon}
    ];
}, $repo->reservoirs($companyId));                                       // represas filtradas pela empresa

ApiResponse::success(
    ['reservoirs' => $list],
    ['company_id' => $companyId]                                         // meta: filtro efetivamente aplicado
);
