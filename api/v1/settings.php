<?php
/** GET /api/v1/settings.php — configurações, empresas, represas e limites. */
/*
 * Chamado por: assets/js/pages/settings.js (dashboard/configuracoes.php).
 * Não recebe parâmetros. Somente leitura: não existe endpoint para salvar configurações.
 * Saída: empresas com suas represas aninhadas + objeto de configurações do sistema.
 */

declare(strict_types=1);

require_once __DIR__ . '/_boot.php';

use Aquapulse\Support\ApiResponse;

[$repo] = aq_api_boot();

$companies = array_map(static function (array $c) use ($repo): array {       // percorre as empresas acrescentando a lista de represas de cada uma
    $c['reservoirs'] = array_map(static fn (array $r): array => [             // versão resumida de cada represa
        'id'       => $r['id'],
        'name'     => $r['name'],
        'city'     => $r['city'],
        'capacity' => $r['capacity_hm3'],
        'telemetry'=> 'Online',                                              // valor fixo demonstrativo
        'status'   => 'Operacional',                                         // valor fixo demonstrativo
    ], $repo->reservoirs($c['id']));                                         // represas desta empresa
    return $c;
}, $repo->companies());

ApiResponse::success([
    'companies' => $companies,
    'settings'  => $repo->settings(),                                        // unidades, indicadores, limites e notificações
]);
