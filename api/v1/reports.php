<?php
/**
 * Relatórios (exportação das leituras em CSV).
 *
 * GET  /api/v1/reports.php?company_id=&reservoir_id=&type=
 *      Lista os relatórios já gerados.
 *
 * GET  /api/v1/reports.php?download=REP-2001
 *      Baixa o CSV do relatório, montado na hora a partir das leituras do banco.
 *
 * POST /api/v1/reports.php   { "reservoir_id", "type", "from", "to" }
 *      Registra um novo relatório (tabela reports).
 *
 * Chamado por assets/js/pages/reports.js (dashboard/relatorios.php).
 */

declare(strict_types=1);

require_once __DIR__ . '/_boot.php';

use Aquapulse\Http\Request;
use Aquapulse\Services\ReportService;
use Aquapulse\Support\ApiResponse;
use Aquapulse\Support\Clock;
use Aquapulse\Support\Container;
use Aquapulse\Support\Validator;

[$repo, $user] = aq_api_boot(['GET', 'POST']);

/* --------------------------------------------------- POST: gerar relatório */

if (Request::isMethod('POST')) {
    $pdo = Container::requireDatabase();
    $body = aq_json_body();

    $texto = static fn (string $campo): string => is_string($body[$campo] ?? null) ? trim($body[$campo]) : '';

    $represa = $repo->reservoir($texto('reservoir_id'));     // null se não existir ou estiver fora do escopo do usuário
    if ($represa === null) {
        ApiResponse::error('VALIDATION_ERROR', 'Verifique os campos informados.', 422, ['reservoir_id' => 'Selecione uma represa.']);
    }

    $id = (new ReportService($pdo))->generate($represa['id'], $texto('type'), $texto('from'), $texto('to'), $user);

    ApiResponse::success(['id' => $id], [], 201);
}

/* ------------------------------------------------------ GET: baixar o CSV */

$download = Validator::query('download');

if ($download !== '') {
    $pdo = Container::requireDatabase();

    $relatorio = null;
    foreach ($repo->reports() as $r) {                       // só os relatórios das represas que o usuário pode ver
        if ($r['id'] === $download) {
            $relatorio = $r;
            break;
        }
    }

    if ($relatorio === null || $relatorio['period_start'] === null) {
        ApiResponse::error('REPORT_NOT_FOUND', 'Relatório não encontrado.', 404);
    }

    $represa = $repo->reservoir($relatorio['reservoir_id']);
    $csv = (new ReportService($pdo))->csv($relatorio, $represa['name'] ?? '');

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="aquapulse-' . $relatorio['id'] . '.csv"');
    header('Cache-Control: no-store');
    echo $csv;
    exit;
}

/* ------------------------------------------------------------ GET: listar */

$companyId   = Validator::companyId($repo->companies());
$reservoirs  = $repo->reservoirs($companyId);
$reservoirId = Validator::reservoirId($reservoirs, true);
$type        = Validator::option('type', Validator::REPORT_TYPES, 'all', 'INVALID_TYPE', 'O tipo de relatório informado não é válido.');

$nomes = array_column($reservoirs, 'name', 'id');
$naEmpresa = static fn (array $r): bool => isset($nomes[$r['reservoir_id']]);

$escopo = array_values(array_filter($repo->reports($reservoirId), $naEmpresa));

$porTipo = array_fill_keys(array_keys(ReportService::TYPES), 0);
foreach ($escopo as $r) {
    if (isset($porTipo[$r['type']])) {
        $porTipo[$r['type']]++;
    }
}

ApiResponse::success([
    'reports' => array_map(static function (array $r) use ($nomes): array {
        return [
            'id'           => $r['id'],
            'name'         => $r['name'],
            'type'         => $r['type'],
            'type_label'   => ReportService::TYPES[$r['type']]['label'] ?? $r['type'],
            'reservoir'    => str_replace('Represa ', '', $nomes[$r['reservoir_id']]),
            'period'       => $r['period'],
            'generated_at' => Clock::dateTime(Clock::parse($r['generated_at'])),
            'owner'        => $r['owner'],
            'icon'         => $r['icon'],
        ];
    }, array_values(array_filter($repo->reports($reservoirId, $type), $naEmpresa))),
    'summary' => ['total' => count($escopo)] + $porTipo,     // contagens reais do contexto selecionado
    'today'   => Clock::now()->format('Y-m-d'),              // limite do seletor de datas
], [
    'company_id'   => $companyId,
    'reservoir_id' => $reservoirId,
    'type'         => $type,
]);
