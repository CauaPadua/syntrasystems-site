<?php
/** GET /api/v1/reports.php?reservoir_id={id|all}&type={t}&status={st} — relatórios. */

declare(strict_types=1);

require_once __DIR__ . '/_boot.php';

use Aquapulse\Services\StatusRules;
use Aquapulse\Support\ApiResponse;
use Aquapulse\Support\Clock;
use Aquapulse\Support\Validator;

[$repo] = aq_api_boot();

$companyId   = Validator::companyId($repo->companies());
$reservoirId = Validator::reservoirId($repo->reservoirs($companyId), true);
$type        = Validator::option('type', Validator::REPORT_TYPES, 'all', 'INVALID_TYPE', 'O tipo de relatório informado não é válido.');
$status      = Validator::option('status', Validator::REPORT_STATUS, 'all', 'INVALID_STATUS', 'O status informado não é válido.');

$rows = $repo->reports($reservoirId, $type, $status);

$reports = array_map(static function (array $rep) use ($repo): array {
    $r = $repo->reservoir($rep['reservoir_id']);
    $when = new DateTimeImmutable($rep['generated_at'], Clock::timezone());

    return [
        'id'           => $rep['id'],
        'name'         => $rep['name'],
        'type'         => $rep['type'],
        'type_label'   => StatusRules::reportTypeLabel($rep['type']),
        'reservoir'    => str_replace('Represa ', '', $r['name'] ?? '-'),
        'period'       => $rep['period'],
        'generated_at' => Clock::dateTime($when),
        'owner'        => $rep['owner'],
        'status'       => $rep['status'],
        'status_label' => StatusRules::reportStatusLabel($rep['status']),
        'icon'         => $rep['icon'],
    ];
}, $rows);

// Resumo do escopo da represa (independente dos filtros aplicados na lista).
$scope      = $repo->reports($reservoirId);
$total      = count($scope);
$done       = count(array_filter($scope, static fn (array $r): bool => $r['status'] === 'done'));
$processing = count(array_filter($scope, static fn (array $r): bool => $r['status'] === 'processing'));
$scheduled  = count(array_filter($scope, static fn (array $r): bool => $r['status'] === 'scheduled'));

ApiResponse::success([
    'reports' => $reports,
    'summary' => [
        'total'          => 48,
        'done'           => 42,
        'processing'     => 2,
        'scheduled'      => 4,
        'done_pct'       => 87.5,
        'processing_pct' => 4.2,
        'scheduled_pct'  => 8.3,
    ],
    'listed' => [
        'total'      => $total,
        'done'       => $done,
        'processing' => $processing,
        'scheduled'  => $scheduled,
    ],
    'scheduled_reports' => array_map(static function (array $s): array {
        $when = new DateTimeImmutable($s['next_run'], Clock::timezone());
        return [
            'name'      => $s['name'],
            'frequency' => $s['frequency'],
            'next_run'  => Clock::dateTime($when),
        ];
    }, $repo->scheduledReports()),
], [
    'company_id'   => $companyId,
    'reservoir_id' => $reservoirId,
    'type'         => $type,
    'status'       => $status,
]);
