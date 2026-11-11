<?php
/**
 * Aquapulse — dados do MODO DEMONSTRAÇÃO (sistema rodando sem banco).
 *
 * Usado apenas quando não há DB_HOST em backend/.env. Não é um banco de dados:
 * os valores são fixos, somente leitura, e representam um instante de exemplo
 * (Support\Clock::DEMO_INSTANT). Com banco configurado este arquivo não é lido.
 *
 * Quem lê: Repositories\Mock\MockMonitoringRepository. Cada chave de primeiro
 * nível equivale a uma tabela.
 */

declare(strict_types=1);

return [

    'companies' => [
        ['id' => 'hidrovale',      'code' => 'HVE-001', 'name' => 'Hidrovale Energia', 'manager' => 'Mariana Costa',  'status' => 'active', 'status_label' => 'Ativa'],
        ['id' => 'aguas-do-norte', 'code' => 'ADN-002', 'name' => 'Águas do Norte',    'manager' => 'Rafael Andrade', 'status' => 'active', 'status_label' => 'Ativa'],
    ],

    /*
     * Cadastro + última leitura de cada grandeza. Cota e volume não aparecem:
     * são calculados a partir do nível, como no sistema com banco.
     */
    'reservoirs' => [
        [
            'id' => 'santa-clara', 'code' => 'RSC-001', 'name' => 'Represa Santa Clara', 'company_id' => 'hidrovale',
            'city' => 'Rio Claro — SP', 'basin' => 'Bacia do Rio Claro',
            'lat' => -22.3875, 'lng' => -47.6922, 'coordinates_label' => '22°23\'15" S, 47°41\'32" W',
            'capacity_hm3' => 1500.0, 'useful_volume_hm3' => 1050.0, 'technical_reserve_hm3' => 184.0, 'daily_consumption_hm3' => 12.5,
            'cota_spill_m' => 565.0, 'cota_alert_m' => 558.0, 'cota_min_m' => 480.0, 'cota_max_m' => 580.0,
            'level_pct' => 82.4, 'inflow_m3s' => 56.2, 'outflow_m3s' => 49.8, 'ph' => 7.2, 'water_temp_c' => 22.6, 'rain_24h_mm' => 18.6,
        ],
        [
            'id' => 'rio-verde', 'code' => 'RRV-002', 'name' => 'Represa Rio Verde', 'company_id' => 'hidrovale',
            'city' => 'Itirapina — SP', 'basin' => 'Bacia do Rio Verde',
            'lat' => -22.2410, 'lng' => -47.8365, 'coordinates_label' => '22°14\'28" S, 47°50\'11" W',
            'capacity_hm3' => 1288.0, 'useful_volume_hm3' => 838.0, 'technical_reserve_hm3' => 142.0, 'daily_consumption_hm3' => 8.7,
            'cota_spill_m' => 552.0, 'cota_alert_m' => 545.0, 'cota_min_m' => 510.0, 'cota_max_m' => 561.1,
            'level_pct' => 76.1, 'inflow_m3s' => 43.8, 'outflow_m3s' => 39.6, 'ph' => 7.4, 'water_temp_c' => 22.1, 'rain_24h_mm' => 12.3,
        ],
        [
            'id' => 'serra-azul', 'code' => 'RSA-003', 'name' => 'Represa Serra Azul', 'company_id' => 'aguas-do-norte',
            'city' => 'Corumbataí — SP', 'basin' => 'Bacia do Corumbataí',
            'lat' => -22.1958, 'lng' => -47.5487, 'coordinates_label' => '22°11\'45" S, 47°32\'55" W',
            'capacity_hm3' => 957.0, 'useful_volume_hm3' => 632.0, 'technical_reserve_hm3' => 108.0, 'daily_consumption_hm3' => 6.9,
            'cota_spill_m' => 608.0, 'cota_alert_m' => 601.0, 'cota_min_m' => 556.0, 'cota_max_m' => 618.2,
            'level_pct' => 77.3, 'inflow_m3s' => 32.5, 'outflow_m3s' => 29.1, 'ph' => 7.3, 'water_temp_c' => 21.8, 'rain_24h_mm' => 8.7,
        ],
    ],

    /* Equipamentos por represa. status: active | inactive | maintenance (situação cadastral);
       online = false simula um equipamento ativo que parou de transmitir. */
    'sensors' => [
        'santa-clara' => [
            ['id' => 'SEN-NIV-01', 'name' => 'Régua de nível',       'location' => 'Barragem principal',   'type' => 'level',   'status' => 'active',      'online' => true],
            ['id' => 'SEN-VAZ-01', 'name' => 'Afluência principal',  'location' => 'Entrada principal',    'type' => 'inflow',  'status' => 'active',      'online' => true],
            ['id' => 'SEN-VAZ-02', 'name' => 'Defluência principal', 'location' => 'Saída da barragem',    'type' => 'outflow', 'status' => 'active',      'online' => true],
            ['id' => 'SEN-VAZ-03', 'name' => 'Canal de restituição', 'location' => 'Canal de restituição', 'type' => 'outflow', 'status' => 'maintenance', 'online' => false],
            ['id' => 'SEN-PH-01',  'name' => 'Sonda de pH',          'location' => 'Tomada d\'água',       'type' => 'ph',      'status' => 'active',      'online' => true],
            ['id' => 'SEN-TMP-01', 'name' => 'Termômetro da água',      'location' => 'Tomada d\'água',     'type' => 'water_temp',    'status' => 'active', 'online' => true],
            ['id' => 'SEN-PLU-01', 'name' => 'Pluviômetro da barragem', 'location' => 'Crista da barragem', 'type' => 'precipitation', 'status' => 'active', 'online' => true],
        ],
        'rio-verde' => [
            ['id' => 'SEN-NIV-11', 'name' => 'Régua de nível',       'location' => 'Barragem principal', 'type' => 'level',   'status' => 'active', 'online' => true],
            ['id' => 'SEN-VAZ-11', 'name' => 'Afluência principal',  'location' => 'Entrada principal',  'type' => 'inflow',  'status' => 'active', 'online' => true],
            ['id' => 'SEN-VAZ-12', 'name' => 'Defluência principal', 'location' => 'Saída da barragem',  'type' => 'outflow', 'status' => 'active', 'online' => true],
            ['id' => 'SEN-PH-11',  'name' => 'Sonda de pH',          'location' => 'Tomada d\'água',     'type' => 'ph',      'status' => 'active', 'online' => true],
            ['id' => 'SEN-TMP-11', 'name' => 'Termômetro da água',      'location' => 'Tomada d\'água',     'type' => 'water_temp',    'status' => 'active', 'online' => true],
            ['id' => 'SEN-PLU-11', 'name' => 'Pluviômetro da barragem', 'location' => 'Crista da barragem', 'type' => 'precipitation', 'status' => 'active', 'online' => true],
        ],
        'serra-azul' => [
            ['id' => 'SEN-NIV-21', 'name' => 'Régua de nível',       'location' => 'Barragem principal', 'type' => 'level',   'status' => 'active', 'online' => true],
            ['id' => 'SEN-VAZ-21', 'name' => 'Afluência principal',  'location' => 'Entrada principal',  'type' => 'inflow',  'status' => 'active', 'online' => true],
            ['id' => 'SEN-VAZ-22', 'name' => 'Defluência principal', 'location' => 'Saída da barragem',  'type' => 'outflow', 'status' => 'active', 'online' => false],
            ['id' => 'SEN-PH-21',  'name' => 'Sonda de pH',          'location' => 'Tomada d\'água',     'type' => 'ph',      'status' => 'active', 'online' => true],
            ['id' => 'SEN-TMP-21', 'name' => 'Termômetro da água',      'location' => 'Tomada d\'água',     'type' => 'water_temp',    'status' => 'active', 'online' => true],
            ['id' => 'SEN-PLU-21', 'name' => 'Pluviômetro da barragem', 'location' => 'Crista da barragem', 'type' => 'precipitation', 'status' => 'active', 'online' => true],
        ],
    ],

    /* Alertas de exemplo, coerentes com os valores acima e com os limites padrão. */
    'alerts' => [
        [
            'id' => 'ALT-1001', 'reservoir_id' => 'santa-clara', 'sensor_id' => 'SEN-NIV-01', 'severity' => 'attention', 'metric_key' => 'level',
            'title' => 'Nível do reservatório em atenção', 'metric' => 'Nível do reservatório',
            'detected_at' => '2024-05-22T08:45:00-03:00', 'resolved_at' => null, 'owner' => 'Não atribuído', 'status' => 'new',
            'current_value' => '82,4%', 'threshold' => '80%',
            'detail' => 'Leitura do equipamento SEN-NIV-01', 'threshold_detail' => 'Limite de atenção',
            'timeline' => [
                ['at' => '2024-05-22T08:45:00-03:00', 'text' => 'Alerta aberto automaticamente: 80,1% (limite 80%).'],
            ],
        ],
        [
            'id' => 'ALT-1000', 'reservoir_id' => 'rio-verde', 'sensor_id' => 'SEN-PH-11', 'severity' => 'attention', 'metric_key' => 'ph',
            'title' => 'pH fora da faixa ideal', 'metric' => 'pH da água',
            'detected_at' => '2024-05-21T06:20:00-03:00', 'resolved_at' => '2024-05-21T07:05:00-03:00', 'owner' => 'Ana Silva', 'status' => 'resolved',
            'current_value' => '8,62', 'threshold' => '6,5 – 8,5',
            'detail' => 'Leitura do equipamento SEN-PH-11', 'threshold_detail' => 'Faixa ideal',
            'timeline' => [
                ['at' => '2024-05-21T06:20:00-03:00', 'text' => 'Alerta aberto automaticamente: 8,62 (limite 6,5 – 8,5).'],
                ['at' => '2024-05-21T06:31:00-03:00', 'text' => 'Alerta assumido por Ana Silva.'],
                ['at' => '2024-05-21T07:05:00-03:00', 'text' => 'Alerta resolvido por Ana Silva.'],
            ],
        ],
    ],

    'reports' => [
        ['id' => 'REP-2001', 'name' => 'Leituras diárias — hidrológico', 'type' => 'hydrological', 'reservoir_id' => 'santa-clara',
         'period' => '16/05/2024 – 22/05/2024', 'period_start' => '2024-05-16', 'period_end' => '2024-05-22',
         'generated_at' => '2024-05-22T07:00:00-03:00', 'owner' => 'Ana Silva', 'icon' => 'droplet'],
    ],

    'maintenances' => [
        'santa-clara' => [
            ['id' => 1, 'date' => '2024-05-24', 'equipment' => 'SEN-VAZ-03 — Canal de restituição', 'type' => 'Corretiva',  'priority' => 'attention', 'description' => 'Equipamento em manutenção; troca do transdutor.'],
            ['id' => 2, 'date' => '2024-05-31', 'equipment' => 'SEN-NIV-01 — Régua de nível',       'type' => 'Calibração', 'priority' => 'low',       'description' => 'Calibração semestral.'],
        ],
        'rio-verde' => [
            ['id' => 3, 'date' => '2024-05-27', 'equipment' => 'SEN-VAZ-12 — Defluência principal', 'type' => 'Preventiva', 'priority' => 'low', 'description' => 'Inspeção do medidor.'],
        ],
        'serra-azul' => [
            ['id' => 4, 'date' => '2024-05-25', 'equipment' => 'SEN-VAZ-22 — Defluência principal', 'type' => 'Corretiva', 'priority' => 'attention', 'description' => 'Sem comunicação.'],
        ],
    ],
];
