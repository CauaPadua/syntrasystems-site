<?php
/**
 * Aquapulse — valores de partida do histórico simulado e contas de acesso.
 *
 * O instalador (backend/database/migrate.php) gera um passado para cada represa
 * terminando nestes valores, no momento da carga. São números de exemplo, não
 * medições reais. Depois da carga, quem atualiza o painel são as leituras
 * enviadas pela estação de telemetria (estacao/estacao.php), vindas do
 * simulador de sensores (simulador-sensor/), que começa do último valor gravado.
 */

declare(strict_types=1);

return [
    // Última leitura de cada grandeza por represa (rain_24h = chuva acumulada em 24 h, em mm).
    'reservoirs' => [
        'santa-clara' => ['level' => 82.4, 'inflow' => 56.2, 'outflow' => 49.8, 'ph' => 7.2, 'water_temp' => 22.6, 'rain_24h' => 18.6],
        'rio-verde'   => ['level' => 64.1, 'inflow' => 43.8, 'outflow' => 39.6, 'ph' => 7.4, 'water_temp' => 22.1, 'rain_24h' => 12.3],
        'serra-azul'  => ['level' => 69.3, 'inflow' => 32.5, 'outflow' => 29.1, 'ph' => 7.3, 'water_temp' => 21.8, 'rain_24h' => 8.7],
    ],

    // Contas de acesso. Todas usam a mesma senha do usuário de demonstração: o
    // hash é copiado de backend/storage/mock/users.php, sem aparecer aqui.
    //   reservoirs = represas que a conta pode ver (o administrador vê todas)
    'users' => [
        ['name' => 'Ana Silva',   'email' => 'demo@aquapulse.local',     'role' => 'admin',    'status' => 'active',    'reservoirs' => []],
        ['name' => 'Bruno Lima',  'email' => 'operador@aquapulse.local', 'role' => 'operator', 'status' => 'active',    'reservoirs' => ['santa-clara', 'rio-verde']],
        ['name' => 'Carla Souza', 'email' => 'suspenso@aquapulse.local', 'role' => 'operator', 'status' => 'suspended', 'reservoirs' => ['santa-clara', 'rio-verde']],
        ['name' => 'Diego Ramos', 'email' => 'gestor@aquapulse.local',   'role' => 'manager',  'status' => 'active',    'reservoirs' => ['serra-azul']],
    ],
];
