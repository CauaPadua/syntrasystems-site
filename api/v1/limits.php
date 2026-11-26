<?php
/**
 * Limites de pH e de chuva.
 *
 * POST /api/v1/limits.php   { "ph_min", "ph_max", "rain_attention", "rain_critical" }   só administrador
 *
 * A leitura dos limites em vigor vem em GET api/v1/settings.php. Os limites do
 * nível são as cotas de cada represa e mudam em api/v1/reservoirs.php. O SQL fica
 * em Repositories\LimitRepository.
 */

declare(strict_types=1);

require_once __DIR__ . '/_boot.php';

use Aquapulse\Repositories\LimitRepository;
use Aquapulse\Support\ApiResponse;
use Aquapulse\Support\Container;
use Aquapulse\Support\Guard;

[, $user] = aq_api_boot(['POST']);

Guard::requireCan($user, 'limits.edit');

(new LimitRepository(Container::requireDatabase()))->update(aq_json_body());

ApiResponse::success([]);
