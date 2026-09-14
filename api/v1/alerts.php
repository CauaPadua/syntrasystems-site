<?php
/** GET /api/v1/alerts.php?reservoir_id={id|all}&severity={s}&status={st} — central de alertas. */
/*
 * Chamado por: assets/js/pages/alerts.js (dashboard/alertas.php).
 * Entrada: company_id, reservoir_id, severity (all|critical|attention|info) e
 *          status (all|new|analysis|resolved), todos opcionais.
 * Saída: lista de alertas formatada, contadores, série do gráfico e canais de notificação.
 * Erros: 400 INVALID_SEVERITY / INVALID_STATUS, 404 INVALID_COMPANY / INVALID_RESERVOIR.
 */

declare(strict_types=1);

require_once __DIR__ . '/_boot.php';

use Aquapulse\Services\StatusRules;
use Aquapulse\Support\ApiResponse;
use Aquapulse\Support\Clock;
use Aquapulse\Support\Validator;

[$repo] = aq_api_boot();

$companyId   = Validator::companyId($repo->companies());
$reservoirId = Validator::reservoirId($repo->reservoirs($companyId), true);
$severity    = Validator::option('severity', Validator::SEVERITY, 'all', 'INVALID_SEVERITY', 'A severidade informada não é válida.');
$status      = Validator::option('status', Validator::ALERT_STATUS, 'all', 'INVALID_STATUS', 'O status informado não é válido.');

$rows = $repo->alerts($reservoirId, $severity, $status);                    // alertas que passam pelos três filtros

$alerts = array_map(static function (array $a) use ($repo): array {         // formata cada alerta para a tabela e o painel de detalhes
    $r = $repo->reservoir($a['reservoir_id']);                              // busca o nome da represa do alerta
    $when = new DateTimeImmutable($a['detected_at'], Clock::timezone());

    return [
        'id'             => $a['id'],
        'title'          => $a['title'],
        'severity'       => $a['severity'],
        'severity_label' => StatusRules::severityLabel($a['severity']),     // "Crítico", "Atenção"...
        'reservoir'      => str_replace('Represa ', '', $r['name'] ?? '-'), // nome curto da represa
        'reservoir_id'   => $a['reservoir_id'],
        'metric'         => $a['metric'],
        'detected_at'    => Clock::dateTime($when),                         // "22/05/2024 08:45"
        'owner'          => $a['owner'],
        'status'         => $a['status'],
        'status_label'   => StatusRules::alertStatusLabel($a['status']),    // "Novo", "Em análise", "Resolvido"
        'current_value'  => $a['current_value'],
        'threshold'      => $a['threshold'],
        'detail'         => $a['detail'],
        'threshold_detail' => $a['threshold_detail'],
        'timeline'       => array_map(static function (array $t): array {   // etapas do tratamento do alerta
            return [
                'at'   => $t['at'] !== null                                 // etapa concluída tem data; pendente mostra "—"
                    ? Clock::dateTime(new DateTimeImmutable($t['at'], Clock::timezone()))
                    : '—',
                'text' => $t['text'],
                'done' => $t['done'],
            ];
        }, $a['timeline']),
    ];
}, $rows);

// As contagens refletem o escopo da represa, não a lista já filtrada.
$scope  = $repo->alerts($reservoirId);                                      // todos os alertas da represa, sem filtro de severidade/status
$counts = ['total' => 0, 'critical' => 0, 'attention' => 0, 'info' => 0];

foreach ($scope as $a) {                                                    // conta apenas os alertas ainda ativos
    if ($a['status'] === 'resolved') {
        continue;
    }
    $counts['total']++;
    if (isset($counts[$a['severity']])) {
        $counts[$a['severity']]++;
    }
}

// Série dos últimos 7 dias por severidade (demonstrativa e determinística).
$labels = [];
$cursor = Clock::now()->modify('-6 days');                                 // começa 6 dias atrás para terminar hoje
for ($i = 0; $i < 7; $i++) {
    $labels[] = Clock::shortDate($cursor);
    $cursor = $cursor->modify('+1 day');
}

ApiResponse::success([
    'alerts' => $alerts,
    'counts' => [                                                           // cards do topo da central de alertas
        'active'      => $counts['total'],
        'critical'    => $counts['critical'],
        'attention'   => $counts['attention'],
        'resolved'    => 8,                                                 // valor fixo demonstrativo
        'avg_minutes' => 12,                                                // tempo médio de resposta: valor fixo demonstrativo
    ],
    'chart' => [                                                            // quantidades por dia: valores fixos demonstrativos
        'labels'    => $labels,
        'critical'  => [2, 1, 1, 2, 3, 3, 1],
        'attention' => [2, 3, 1, 2, 3, 3, 2],
        'info'      => [2, 2, 1, 2, 2, 2, 1],
        'resolved'  => [0, 0, 0, 0, 0, 0, 3],
    ],
    'channels' => $repo->settings()['notifications'] ?? [],                 // canais de notificação (e-mail, painel)
], [
    'company_id'   => $companyId,
    'reservoir_id' => $reservoirId,
    'severity'     => $severity,
    'status'       => $status,
]);
