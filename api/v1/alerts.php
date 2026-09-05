<?php
/** GET /api/v1/alerts.php?reservoir_id={id|all}&severity={s}&status={st} — central de alertas. */

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

$rows = $repo->alerts($reservoirId, $severity, $status);

$alerts = array_map(static function (array $a) use ($repo): array {
    $r = $repo->reservoir($a['reservoir_id']);
    $when = new DateTimeImmutable($a['detected_at'], Clock::timezone());

    return [
        'id'             => $a['id'],
        'title'          => $a['title'],
        'severity'       => $a['severity'],
        'severity_label' => StatusRules::severityLabel($a['severity']),
        'reservoir'      => str_replace('Represa ', '', $r['name'] ?? '-'),
        'reservoir_id'   => $a['reservoir_id'],
        'metric'         => $a['metric'],
        'detected_at'    => Clock::dateTime($when),
        'owner'          => $a['owner'],
        'status'         => $a['status'],
        'status_label'   => StatusRules::alertStatusLabel($a['status']),
        'current_value'  => $a['current_value'],
        'threshold'      => $a['threshold'],
        'detail'         => $a['detail'],
        'threshold_detail' => $a['threshold_detail'],
        'timeline'       => array_map(static function (array $t): array {
            return [
                'at'   => $t['at'] !== null
                    ? Clock::dateTime(new DateTimeImmutable($t['at'], Clock::timezone()))
                    : '—',
                'text' => $t['text'],
                'done' => $t['done'],
            ];
        }, $a['timeline']),
    ];
}, $rows);

// As contagens refletem o escopo da represa, não a lista já filtrada.
$scope  = $repo->alerts($reservoirId);
$counts = ['total' => 0, 'critical' => 0, 'attention' => 0, 'info' => 0];

foreach ($scope as $a) {
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
$cursor = Clock::now()->modify('-6 days');
for ($i = 0; $i < 7; $i++) {
    $labels[] = Clock::shortDate($cursor);
    $cursor = $cursor->modify('+1 day');
}

ApiResponse::success([
    'alerts' => $alerts,
    'counts' => [
        'active'      => $counts['total'],
        'critical'    => $counts['critical'],
        'attention'   => $counts['attention'],
        'resolved'    => 8,
        'avg_minutes' => 12,
    ],
    'chart' => [
        'labels'    => $labels,
        'critical'  => [2, 1, 1, 2, 3, 3, 1],
        'attention' => [2, 3, 1, 2, 3, 3, 2],
        'info'      => [2, 2, 1, 2, 2, 2, 1],
        'resolved'  => [0, 0, 0, 0, 0, 0, 3],
    ],
    'channels' => $repo->settings()['notifications'] ?? [],
], [
    'company_id'   => $companyId,
    'reservoir_id' => $reservoirId,
    'severity'     => $severity,
    'status'       => $status,
]);
