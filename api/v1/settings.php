<?php
/**
 * GET /api/v1/settings.php — dados da tela de configurações.
 *
 * Devolve as empresas com suas represas, os limites operacionais em vigor e,
 * para o administrador, as contas de acesso e as listas dos formulários. As
 * gravações usam companies.php, reservoirs.php e users.php.
 *
 * Chamado por assets/js/pages/settings.js (dashboard/configuracoes.php).
 */

declare(strict_types=1);

require_once __DIR__ . '/_boot.php';

use Aquapulse\Repositories\AccountRepository;
use Aquapulse\Services\StatusRules;
use Aquapulse\Support\ApiResponse;
use Aquapulse\Support\Clock;
use Aquapulse\Support\Container;
use Aquapulse\Support\Guard;

[$repo, $user] = aq_api_boot();

$podeCadastrar = Guard::can($user, 'registry.edit');

// Campos do cadastro da represa, com os mesmos nomes do formulário.
$cadastro = ['id', 'code', 'company_id', 'name', 'city', 'basin', 'lat', 'lng', 'coordinates_label',
             'capacity_hm3', 'useful_volume_hm3', 'technical_reserve_hm3', 'daily_consumption_hm3',
             'cota_min_m', 'cota_max_m', 'cota_alert_m', 'cota_spill_m'];

$companies = array_map(static function (array $c) use ($repo, $cadastro): array {
    $c['reservoirs'] = array_map(static fn (array $r): array => array_intersect_key($r, array_flip($cadastro)) + [
        'sensors_online' => $r['sensors_online'],
        'sensors_total'  => $r['sensors_total'],
        'status'         => StatusRules::describe(StatusRules::fromLevel($r['level_pct'])),
    ], $repo->reservoirs($c['id']));
    return $c;
}, $repo->companies());

// A lista de contas só é entregue ao administrador.
$users = [];
if ($podeCadastrar) {
    $nomes = array_column($repo->reservoirs(), 'name', 'id');

    $users = array_map(static fn (array $u): array => [
        'id'           => $u['id'],
        'name'         => $u['name'],
        'email'        => $u['email'],
        'role'         => $u['role'],
        'role_label'   => Guard::roleLabel($u['role']),
        'status'       => $u['status'],
        'status_label' => AccountRepository::STATUS[$u['status']] ?? $u['status'],
        'last_login'   => $u['last_login_at'] === null ? 'Nunca acessou' : Clock::dateTime(Clock::parse($u['last_login_at'])),
        'reservoirs'   => $u['reservoir_ids'],
        'reservoir_names' => $u['role'] === 'admin'
            ? ['Todas']
            : array_values(array_map(static fn (string $id): string => str_replace('Represa ', '', $nomes[$id] ?? $id), $u['reservoir_ids'])),
        'is_self'      => $u['id'] === (int) $user['id'],
    ], Container::users()->all());
}

$opcoes = static fn (array $lista): array => array_map(
    static fn (string $v, string $l): array => ['value' => $v, 'label' => $l],
    array_keys($lista),
    $lista
);

ApiResponse::success([
    'companies' => $companies,
    'limits'    => [
        ['label' => 'Nível — atenção',  'value' => 'a partir de ' . StatusRules::pct(StatusRules::levelAttention())],
        ['label' => 'Nível — crítico',  'value' => 'a partir de ' . StatusRules::pct(StatusRules::levelCritical())],
        ['label' => 'pH — faixa ideal', 'value' => StatusRules::number(StatusRules::phMin()) . ' a ' . StatusRules::number(StatusRules::phMax())],
        ['label' => 'Chuva em 24 h — atenção', 'value' => 'a partir de ' . StatusRules::number(StatusRules::rainAttention()) . ' mm'],
        ['label' => 'Chuva em 24 h — crítico', 'value' => 'a partir de ' . StatusRules::number(StatusRules::rainCritical()) . ' mm'],
    ],
    'users'    => $users,
    'can_edit' => $podeCadastrar,
    'roles'    => $opcoes(Guard::ROLES),
    'statuses' => $opcoes(AccountRepository::STATUS),
]);
