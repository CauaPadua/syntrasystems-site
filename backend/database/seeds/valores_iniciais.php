<?php
/**
 * Aquapulse — valores de partida do histórico simulado.
 *
 * O instalador (backend/database/migrate.php) gera um passado para cada represa
 * terminando nestes valores, no momento da carga. São números de exemplo, não
 * medições reais. Depois da carga, quem atualiza o painel são as leituras
 * recebidas pela API de ingestão.
 */

declare(strict_types=1);

return [
    // Última leitura de cada grandeza por represa (rain_24h = chuva acumulada em 24 h, em mm).
    'reservoirs' => [
        'santa-clara' => ['level' => 82.4, 'inflow' => 56.2, 'outflow' => 49.8, 'ph' => 7.2, 'water_temp' => 22.6, 'rain_24h' => 18.6],
        'rio-verde'   => ['level' => 76.1, 'inflow' => 43.8, 'outflow' => 39.6, 'ph' => 7.4, 'water_temp' => 22.1, 'rain_24h' => 12.3],
        'serra-azul'  => ['level' => 77.3, 'inflow' => 32.5, 'outflow' => 29.1, 'ph' => 7.3, 'water_temp' => 21.8, 'rain_24h' => 8.7],
    ],

    // pH de cada ponto de coleta: [represa => [nome do ponto => pH]].
    'ph_points' => [
        'santa-clara' => ['Entrada principal' => 7.1, 'Centro do reservatório' => 7.2, 'Próximo à barragem' => 7.3],
        'rio-verde'   => ['Entrada principal' => 7.3, 'Centro do reservatório' => 7.4, 'Próximo à barragem' => 7.5],
        'serra-azul'  => ['Entrada principal' => 7.2, 'Centro do reservatório' => 7.3, 'Próximo à barragem' => 7.4],
    ],

    // Chuva de 24 h de cada estação pluviométrica (mm).
    'rain_stations' => [
        'P01' => 12.3, 'P02' => 8.7, 'P03' => 18.6,
        'P11' => 12.3, 'P12' => 9.4,
        'P21' => 8.7,  'P22' => 6.2,
    ],

    // Contas de acesso. Todas usam a mesma senha do usuário de demonstração: o
    // hash é copiado de backend/storage/mock/users.php, sem aparecer aqui.
    //   companies = empresas que a conta pode consultar (administrador vê todas)
    'users' => [
        ['id' => 1, 'name' => 'Ana Silva',   'email' => 'demo@aquapulse.local',     'role' => 'admin',    'status' => 'active',    'companies' => ['hidrovale', 'aguas-do-norte']],
        ['id' => 2, 'name' => 'Bruno Lima',  'email' => 'operador@aquapulse.local', 'role' => 'operator', 'status' => 'active',    'companies' => ['hidrovale']],
        ['id' => 3, 'name' => 'Carla Souza', 'email' => 'suspenso@aquapulse.local', 'role' => 'operator', 'status' => 'suspended', 'companies' => ['hidrovale']],
    ],
];
