<?php
/**
 * GET /api/v1/settings.php — dados da tela de configurações (somente leitura).
 *
 * Devolve as empresas com suas represas, os limites operacionais em vigor e,
 * para administradores, as contas de acesso.
 *
 * Chamado por assets/js/pages/settings.js (dashboard/configuracoes.php).
 */

declare(strict_types=1);

require_once __DIR__ . '/_boot.php';

use Aquapulse\Services\StatusRules;
use Aquapulse\Support\ApiResponse;
use Aquapulse\Support\Clock;
use Aquapulse\Support\Container;
use Aquapulse\Support\Guard;

[$repo, $user] = aq_api_boot();

$companies = array_map(static function (array $c) use ($repo): array {
    $c['reservoirs'] = array_map(static fn (array $r): array => [
        'id'             => $r['id'],
        'name'           => $r['name'],
        'city'           => $r['city'],
        'capacity'       => $r['capacity_hm3'],
        'sensors_online' => $r['sensors_online'],
        'sensors_total'  => $r['sensors_total'],
        'status'         => StatusRules::describe(StatusRules::fromLevel((float) $r['level_pct'])),
    ], $repo->reservoirs($c['id']));
    return $c;
}, $repo->companies());

// A lista de contas só é entregue a administradores.
$users = [];
if (Guard::isAdmin($user)) {
    $users = array_map(static fn (array $u): array => [
        'name'         => $u['name'],
        'email'        => $u['email'],
        'role_label'   => Guard::roleLabel($u['role']),
        'status'       => $u['status'],
        'status_label' => $u['status'] === 'active' ? 'Ativo' : 'Suspenso',
        'last_login'   => $u['last_login_at'] === null ? 'Nunca acessou' : Clock::dateTime(Clock::parse($u['last_login_at'])),
    ], Container::users()->all());
}

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
    'is_admin' => Guard::isAdmin($user),
    'profile'  => ['name' => $user['name'], 'email' => $user['email'], 'role_label' => Guard::roleLabel($user['role'])],
]);
