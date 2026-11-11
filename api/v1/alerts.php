<?php
/**
 * Central de alertas.
 *
 * GET  /api/v1/alerts.php?company_id=&reservoir_id=&severity=&status=
 *      Lista os alertas e os números do topo da tela.
 *
 * POST /api/v1/alerts.php   { "action": "acknowledge" | "resolve", "id": "ALT-1001" }
 *      Assume ou resolve um alerta. A mudança é gravada no banco (tabelas
 *      alerts e alert_timeline) por Services\AlertService.
 *
 * Chamado por assets/js/pages/alerts.js (dashboard/alertas.php).
 */

declare(strict_types=1);

require_once __DIR__ . '/_boot.php';

use Aquapulse\Http\Request;
use Aquapulse\Services\AlertService;
use Aquapulse\Services\StatusRules;
use Aquapulse\Support\ApiResponse;
use Aquapulse\Support\Clock;
use Aquapulse\Support\Container;
use Aquapulse\Support\Validator;

[$repo, $user] = aq_api_boot(['GET', 'POST']);

/* ------------------------------------------------------- POST: tratar alerta */

if (Request::isMethod('POST')) {
    $pdo = Container::requireDatabase();
    $body = aq_json_body();

    $id = is_string($body['id'] ?? null) ? $body['id'] : '';
    $acao = is_string($body['action'] ?? null) ? $body['action'] : '';

    // O alerta precisa estar entre os que o usuário pode ver.
    $visiveis = array_column($repo->alerts(), 'id');
    if (!in_array($id, $visiveis, true)) {
        ApiResponse::error('ALERT_NOT_FOUND', 'Alerta não encontrado.', 404);
    }

    $service = new AlertService($pdo);

    if ($acao === 'acknowledge') {
        $service->acknowledge($id, $user);
    } elseif ($acao === 'resolve') {
        $service->resolve($id, $user);
    } else {
        ApiResponse::error('INVALID_ACTION', 'Ação inválida. Use "acknowledge" ou "resolve".', 400);
    }

    ApiResponse::success(['id' => $id, 'action' => $acao]);
}

/* ------------------------------------------------------------- GET: listar */

$companyId   = Validator::companyId($repo->companies());
$reservoirs  = $repo->reservoirs($companyId);
$reservoirId = Validator::reservoirId($reservoirs, true);
$severity    = Validator::option('severity', Validator::SEVERITY, 'all', 'INVALID_SEVERITY', 'A severidade informada não é válida.');
$status      = Validator::option('status', Validator::ALERT_STATUS, 'all', 'INVALID_STATUS', 'O status informado não é válido.');

$nomes = array_column($reservoirs, 'name', 'id');           // [id da represa => nome]
$naEmpresa = static fn (array $a): bool => isset($nomes[$a['reservoir_id']]);
$data = static fn (?string $iso): string => $iso === null ? '—' : Clock::dateTime(Clock::parse($iso));

$alerts = array_map(static function (array $a) use ($nomes, $data): array {
    return [
        'id'               => $a['id'],
        'title'            => $a['title'],
        'severity'         => $a['severity'],
        'severity_label'   => StatusRules::severityLabel($a['severity']),
        'reservoir'        => str_replace('Represa ', '', $nomes[$a['reservoir_id']]),
        'reservoir_id'     => $a['reservoir_id'],
        'equipment'        => $a['sensor_id'] ?? '—',
        'metric'           => $a['metric'],
        'detected_at'      => $data($a['detected_at']),
        'owner'            => $a['owner'],
        'status'           => $a['status'],
        'status_label'     => StatusRules::alertStatusLabel($a['status']),
        'current_value'    => $a['current_value'],
        'threshold'        => $a['threshold'],
        'detail'           => $a['detail'],
        'threshold_detail' => $a['threshold_detail'],
        'timeline'         => array_map(static fn (array $t): array => [
            'at'   => $data($t['at']),
            'text' => $t['text'],
        ], $a['timeline']),
    ];
}, array_values(array_filter($repo->alerts($reservoirId, $severity, $status), $naEmpresa)));

/* Os números do topo e o gráfico consideram a represa/empresa escolhida, não
   os filtros de severidade e status aplicados à lista. */
$escopo = array_values(array_filter($repo->alerts($reservoirId), $naEmpresa));

$agora = Clock::now();
$inicioDaSemana = $agora->modify('-6 days')->setTime(0, 0);

$counts = ['active' => 0, 'critical' => 0, 'attention' => 0, 'resolved' => 0];
$minutos = [];

// Um contador por dia e situação, para o gráfico dos últimos 7 dias.
$dias = [];
for ($i = 0; $i < 7; $i++) {
    $dias[$inicioDaSemana->modify("+{$i} days")->format('Y-m-d')] = ['critical' => 0, 'attention' => 0, 'resolved' => 0];
}

foreach ($escopo as $a) {
    $detectado = Clock::parse($a['detected_at']);

    if ($a['status'] !== 'resolved') {
        $counts['active']++;
        if (isset($counts[$a['severity']])) {
            $counts[$a['severity']]++;
        }
    }

    $dia = $detectado->format('Y-m-d');
    if (isset($dias[$dia][$a['severity']])) {
        $dias[$dia][$a['severity']]++;                      // alertas ABERTOS naquele dia, por severidade
    }

    if ($a['resolved_at'] !== null) {
        $resolvido = Clock::parse($a['resolved_at']);
        if ($resolvido >= $inicioDaSemana) {
            $counts['resolved']++;
            $minutos[] = ($resolvido->getTimestamp() - $detectado->getTimestamp()) / 60;
            $dias[$resolvido->format('Y-m-d')]['resolved']++;   // alertas RESOLVIDOS naquele dia
        }
    }
}

ApiResponse::success([
    'alerts' => $alerts,
    'counts' => $counts + [
        // tempo médio entre a detecção e a resolução, nos alertas resolvidos em 7 dias
        'avg_minutes' => $minutos === [] ? null : (int) round(array_sum($minutos) / count($minutos)),
    ],
    'chart' => [
        'labels'    => array_map(static fn (string $d): string => Clock::shortDate(Clock::parse($d)), array_keys($dias)),
        'critical'  => array_column($dias, 'critical'),
        'attention' => array_column($dias, 'attention'),
        'resolved'  => array_column($dias, 'resolved'),
    ],
    'period' => $inicioDaSemana->format('d/m/Y') . ' → ' . $agora->format('d/m/Y'),
], [
    'company_id'   => $companyId,
    'reservoir_id' => $reservoirId,
    'severity'     => $severity,
    'status'       => $status,
]);
