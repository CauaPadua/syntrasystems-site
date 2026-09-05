<?php
/**
 * Aquapulse — fonte de dados SIMULADOS do sistema de monitoramento.
 *
 * ATENÇÃO — ESTA É A ÚNICA FONTE DE VERDADE DOS DADOS DEMONSTRATIVOS.
 *
 *  - Não é um banco de dados. Não há escrita, cadastro nem persistência.
 *  - Fica fora da pasta pública (backend/ é bloqueado por .htaccess).
 *  - Todos os valores são FIXOS e DETERMINÍSTICOS: nenhuma chamada a rand(),
 *    time() ou similar. A mesma requisição sempre devolve a mesma resposta.
 *  - As séries temporais são derivadas destes âncoras pelo repositório, sempre
 *    terminando exatamente no valor atual do KPI correspondente.
 *
 * SUBSTITUIÇÃO PELO BANCO: ver docs/database-handoff.md. Este arquivo será
 * descartado junto com o MockMonitoringRepository; o PdoMonitoringRepository
 * deverá devolver as mesmas estruturas.
 *
 * COORDENADAS: são DEMONSTRATIVAS (região de Rio Claro/SP) e deverão ser
 * substituídas pelas coordenadas reais vindas do banco.
 */

declare(strict_types=1);

return [

    /* ------------------------------------------------------------ empresas */
    'companies' => [
        [
            'id'        => 'hidrovale',
            'code'      => 'HVE-001',
            'name'      => 'Hidrovale Energia',
            'manager'   => 'Mariana Costa',
            'status'    => 'active',
            'status_label' => 'Ativa',
        ],
        [
            'id'        => 'aguas-do-norte',
            'code'      => 'ADN-002',
            'name'      => 'Águas do Norte',
            'manager'   => 'Rafael Andrade',
            'status'    => 'active',
            'status_label' => 'Ativa',
        ],
    ],

    /* ------------------------------------------------------------ represas */
    /*
     * Valores centrais definidos na especificação da etapa. Todos os cards,
     * gráficos e tabelas derivam destes números — nada é recalculado de forma
     * divergente em outro lugar.
     */
    'reservoirs' => [
        [
            'id'                 => 'santa-clara',
            'code'               => 'RSC-001',
            'name'               => 'Represa Santa Clara',
            'company_id'         => 'hidrovale',
            'city'               => 'Rio Claro — SP',
            'basin'              => 'Bacia do Rio Claro',
            'lat'                => -22.3875,
            'lng'                => -47.6922,
            'coordinates_label'  => '22°23\'15" S, 47°41\'32" W',

            'level_pct'          => 82.4,
            'volume_hm3'         => 1234.0,
            'capacity_hm3'       => 1500.0,
            'flow_m3s'           => 56.2,
            'inflow_m3s'         => 56.2,
            'outflow_m3s'        => 49.8,
            'ph'                 => 7.2,
            'ph_min'             => 6.8,
            'ph_max'             => 7.6,
            'rain_24h_mm'        => 18.6,
            'rain_7d_mm'         => 74.2,
            'rain_month_mm'      => 186.5,
            'duration_days'      => 84,
            'status'             => 'attention',

            'cota_m'             => 562.4,
            'cota_variation_m'   => 0.8,
            'cota_critical_m'    => 570.0,
            'cota_spill_m'       => 565.0,
            'cota_alert_m'       => 558.0,
            // a cota de atenção não é guardada: é derivada do limite de 80%
            // em MonitoringService::cota(), para não divergir do percentual

            'useful_volume_hm3'  => 1050.0,
            'technical_reserve_hm3' => 184.0,
            'daily_consumption_hm3' => 12.5,
            'forecast_reliability_pct' => 92,

            'sensors_online'     => 18,
            'sensors_total'      => 18,
            'gates_online'       => 4,
            'gates_total'        => 4,
            'availability_pct'   => 98.7,
            'telemetry_pct'      => 100,
            'communication_pct'  => 99,
            'power_pct'          => 100,
            'humidity_pct'       => 78,
            'last_reading_time'  => '09:28',
            'water_temp_c'       => 22.6,
        ],
        [
            'id'                 => 'rio-verde',
            'code'               => 'RRV-002',
            'name'               => 'Represa Rio Verde',
            'company_id'         => 'hidrovale',
            'city'               => 'Itirapina — SP',
            'basin'              => 'Bacia do Rio Verde',
            'lat'                => -22.2410,
            'lng'                => -47.8365,
            'coordinates_label'  => '22°14\'28" S, 47°50\'11" W',

            'level_pct'          => 76.1,
            'volume_hm3'         => 980.0,
            'capacity_hm3'       => 1288.0,
            'flow_m3s'           => 43.8,
            'inflow_m3s'         => 43.8,
            'outflow_m3s'        => 39.6,
            'ph'                 => 7.4,
            'ph_min'             => 7.0,
            'ph_max'             => 7.7,
            'rain_24h_mm'        => 12.3,
            'rain_7d_mm'         => 52.8,
            'rain_month_mm'      => 141.2,
            'duration_days'      => 96,
            'status'             => 'normal',

            'cota_m'             => 548.9,
            'cota_variation_m'   => 0.4,
            'cota_critical_m'    => 556.0,
            'cota_spill_m'       => 552.0,
            'cota_alert_m'       => 545.0,

            'useful_volume_hm3'  => 838.0,
            'technical_reserve_hm3' => 142.0,
            'daily_consumption_hm3' => 8.7,
            'forecast_reliability_pct' => 90,

            'sensors_online'     => 14,
            'sensors_total'      => 14,
            'gates_online'       => 3,
            'gates_total'        => 3,
            'availability_pct'   => 99.2,
            'telemetry_pct'      => 100,
            'communication_pct'  => 100,
            'power_pct'          => 100,
            'humidity_pct'       => 71,
            'last_reading_time'  => '09:26',
            'water_temp_c'       => 22.1,
        ],
        [
            'id'                 => 'serra-azul',
            'code'               => 'RSA-003',
            'name'               => 'Represa Serra Azul',
            'company_id'         => 'aguas-do-norte',
            'city'               => 'Corumbataí — SP',
            'basin'              => 'Bacia do Corumbataí',
            'lat'                => -22.1958,
            'lng'                => -47.5487,
            'coordinates_label'  => '22°11\'45" S, 47°32\'55" W',

            'level_pct'          => 77.3,
            'volume_hm3'         => 740.0,
            'capacity_hm3'       => 957.0,
            'flow_m3s'           => 32.5,
            'inflow_m3s'         => 32.5,
            'outflow_m3s'        => 29.1,
            'ph'                 => 7.3,
            'ph_min'             => 6.9,
            'ph_max'             => 7.5,
            'rain_24h_mm'        => 8.7,
            'rain_7d_mm'         => 38.4,
            'rain_month_mm'      => 112.8,
            'duration_days'      => 91,
            'status'             => 'normal',

            'cota_m'             => 604.1,
            'cota_variation_m'   => 0.3,
            'cota_critical_m'    => 612.0,
            'cota_spill_m'       => 608.0,
            'cota_alert_m'       => 601.0,

            'useful_volume_hm3'  => 632.0,
            'technical_reserve_hm3' => 108.0,
            'daily_consumption_hm3' => 6.9,
            'forecast_reliability_pct' => 89,

            'sensors_online'     => 11,
            'sensors_total'      => 12,
            'gates_online'       => 2,
            'gates_total'        => 2,
            'availability_pct'   => 97.4,
            'telemetry_pct'      => 92,
            'communication_pct'  => 100,
            'power_pct'          => 100,
            'humidity_pct'       => 68,
            'last_reading_time'  => '09:24',
            'water_temp_c'       => 21.8,
        ],
    ],

    /* -------------------------------------------------- sensores por represa */
    'sensors' => [
        'santa-clara' => [
            ['id' => 'SEN-VAZ-01', 'name' => 'Afluência principal', 'location' => 'Entrada principal', 'type' => 'flow', 'status' => 'online'],
            ['id' => 'SEN-VAZ-02', 'name' => 'Defluência principal', 'location' => 'Saída da barragem', 'type' => 'flow', 'status' => 'online'],
            ['id' => 'SEN-VAZ-03', 'name' => 'Canal de restituição', 'location' => 'Canal de restituição', 'type' => 'flow', 'status' => 'online'],
        ],
        'rio-verde' => [
            ['id' => 'SEN-VAZ-11', 'name' => 'Afluência principal', 'location' => 'Entrada principal', 'type' => 'flow', 'status' => 'online'],
            ['id' => 'SEN-VAZ-12', 'name' => 'Defluência principal', 'location' => 'Saída da barragem', 'type' => 'flow', 'status' => 'online'],
        ],
        'serra-azul' => [
            ['id' => 'SEN-VAZ-21', 'name' => 'Afluência principal', 'location' => 'Entrada principal', 'type' => 'flow', 'status' => 'online'],
            ['id' => 'SEN-VAZ-22', 'name' => 'Defluência principal', 'location' => 'Saída da barragem', 'type' => 'flow', 'status' => 'offline'],
        ],
    ],

    /* ------------------------------------------- pontos de coleta de pH */
    'ph_points' => [
        'santa-clara' => [
            ['name' => 'Entrada principal',    'ph' => 7.1, 'status' => 'normal'],
            ['name' => 'Centro do reservatório', 'ph' => 7.2, 'status' => 'normal'],
            ['name' => 'Próximo à barragem',   'ph' => 7.3, 'status' => 'normal'],
        ],
        'rio-verde' => [
            ['name' => 'Entrada principal',    'ph' => 7.3, 'status' => 'normal'],
            ['name' => 'Centro do reservatório', 'ph' => 7.4, 'status' => 'normal'],
            ['name' => 'Próximo à barragem',   'ph' => 7.5, 'status' => 'normal'],
        ],
        'serra-azul' => [
            ['name' => 'Entrada principal',    'ph' => 7.2, 'status' => 'normal'],
            ['name' => 'Centro do reservatório', 'ph' => 7.3, 'status' => 'normal'],
            ['name' => 'Próximo à barragem',   'ph' => 7.4, 'status' => 'normal'],
        ],
    ],

    /* --------------------------------------- estações pluviométricas */
    'rain_stations' => [
        'santa-clara' => [
            ['id' => 'P01', 'name' => 'Pluviômetro Norte', 'rain_24h' => 12.3, 'status' => 'online'],
            ['id' => 'P02', 'name' => 'Pluviômetro Oeste', 'rain_24h' => 8.7,  'status' => 'online'],
            ['id' => 'P03', 'name' => 'Pluviômetro Sul',   'rain_24h' => 18.6, 'status' => 'online'],
        ],
        'rio-verde' => [
            ['id' => 'P11', 'name' => 'Pluviômetro Central', 'rain_24h' => 12.3, 'status' => 'online'],
            ['id' => 'P12', 'name' => 'Pluviômetro Leste',   'rain_24h' => 9.4,  'status' => 'online'],
        ],
        'serra-azul' => [
            ['id' => 'P21', 'name' => 'Pluviômetro Serra',  'rain_24h' => 8.7, 'status' => 'online'],
            ['id' => 'P22', 'name' => 'Pluviômetro Vale',   'rain_24h' => 6.2, 'status' => 'offline'],
        ],
    ],

    /* ------------------------------------------------------------- alertas */
    'alerts' => [
        [
            'id' => 'ALT-1001', 'reservoir_id' => 'santa-clara', 'severity' => 'attention',
            'title' => 'Nível acima de 80%', 'metric' => 'Nível do reservatório',
            'detected_at' => '2024-05-22T08:45:00-03:00', 'owner' => 'Ana Silva', 'status' => 'new',
            'current_value' => '82,4%', 'threshold' => '80%',
            'detail' => 'Em relação à cota 562,4 m', 'threshold_detail' => 'Cota 560,0 m',
            'timeline' => [
                ['at' => '2024-05-22T08:45:00-03:00', 'text' => 'Alerta detectado: nível acima de 80% do limite configurado.', 'done' => true],
                ['at' => '2024-05-22T08:47:00-03:00', 'text' => 'Notificação enviada por e-mail e painel.', 'done' => true],
                ['at' => null, 'text' => 'Aguardando análise e ação.', 'done' => false],
            ],
        ],
        [
            'id' => 'ALT-1002', 'reservoir_id' => 'santa-clara', 'severity' => 'critical',
            'title' => 'Vazão afluente elevada', 'metric' => 'Vazão afluente',
            'detected_at' => '2024-05-22T08:30:00-03:00', 'owner' => 'Ana Silva', 'status' => 'analysis',
            'current_value' => '56,2 m³/s', 'threshold' => '55,0 m³/s',
            'detail' => 'Média nas últimas 24h', 'threshold_detail' => 'Limite operacional',
            'timeline' => [
                ['at' => '2024-05-22T08:30:00-03:00', 'text' => 'Alerta detectado: vazão afluente acima do limite.', 'done' => true],
                ['at' => '2024-05-22T08:35:00-03:00', 'text' => 'Equipe de operação notificada.', 'done' => true],
                ['at' => null, 'text' => 'Em análise pela equipe técnica.', 'done' => false],
            ],
        ],
        [
            'id' => 'ALT-1003', 'reservoir_id' => 'santa-clara', 'severity' => 'attention',
            'title' => 'Precipitação intensa prevista', 'metric' => 'Precipitação (24h)',
            'detected_at' => '2024-05-22T07:30:00-03:00', 'owner' => 'Ana Silva', 'status' => 'new',
            'current_value' => '18,6 mm', 'threshold' => '60 mm',
            'detail' => 'Chuva moderada na bacia', 'threshold_detail' => 'Limite crítico configurado',
            'timeline' => [
                ['at' => '2024-05-22T07:30:00-03:00', 'text' => 'Previsão meteorológica indica chuva intensa em 48h.', 'done' => true],
                ['at' => null, 'text' => 'Aguardando confirmação da estação.', 'done' => false],
            ],
        ],
        [
            'id' => 'ALT-1004', 'reservoir_id' => 'rio-verde', 'severity' => 'info',
            'title' => 'pH fora da faixa ideal', 'metric' => 'pH da água',
            'detected_at' => '2024-05-22T06:20:00-03:00', 'owner' => 'Ana Silva', 'status' => 'resolved',
            'current_value' => '7,4', 'threshold' => '6,5 – 8,5',
            'detail' => 'Leitura pontual acima da média', 'threshold_detail' => 'Faixa ideal configurada',
            'timeline' => [
                ['at' => '2024-05-22T06:20:00-03:00', 'text' => 'Leitura registrada fora da média histórica.', 'done' => true],
                ['at' => null, 'text' => 'Coleta manual agendada.', 'done' => false],
            ],
        ],
        [
            'id' => 'ALT-1005', 'reservoir_id' => 'serra-azul', 'severity' => 'info',
            'title' => 'Sensor sem comunicação', 'metric' => 'Vazão defluente',
            'detected_at' => '2024-05-22T05:10:00-03:00', 'owner' => 'Ana Silva', 'status' => 'resolved',
            'current_value' => 'SEN-VAZ-22', 'threshold' => 'Telemetria',
            'detail' => 'Sensor de defluência offline', 'threshold_detail' => 'Reconexão automática',
            'timeline' => [
                ['at' => '2024-05-22T05:10:00-03:00', 'text' => 'Sensor deixou de responder.', 'done' => true],
                ['at' => '2024-05-22T05:40:00-03:00', 'text' => 'Link de backup ativado.', 'done' => true],
                ['at' => '2024-05-22T06:05:00-03:00', 'text' => 'Ocorrência resolvida.', 'done' => true],
            ],
        ],
    ],

    /* ---------------------------------------------------------- relatórios */
    'reports' => [
        ['id' => 'REP-2001', 'name' => 'Resumo diário', 'type' => 'operational', 'reservoir_id' => 'santa-clara', 'period' => '21/05/2024', 'generated_at' => '2024-05-22T07:00:00-03:00', 'owner' => 'Ana Silva', 'status' => 'done', 'icon' => 'file-text'],
        ['id' => 'REP-2002', 'name' => 'Boletim hidrológico', 'type' => 'hydrological', 'reservoir_id' => 'santa-clara', 'period' => 'Maio/2024', 'generated_at' => '2024-05-22T06:30:00-03:00', 'owner' => 'Ana Silva', 'status' => 'done', 'icon' => 'droplet'],
        ['id' => 'REP-2003', 'name' => 'Comparativo de vazão', 'type' => 'hydrological', 'reservoir_id' => 'santa-clara', 'period' => '15/05 – 22/05/2024', 'generated_at' => '2024-05-22T06:15:00-03:00', 'owner' => 'Ana Silva', 'status' => 'done', 'icon' => 'waves'],
        ['id' => 'REP-2004', 'name' => 'Qualidade da água', 'type' => 'quality', 'reservoir_id' => 'santa-clara', 'period' => '21/05/2024', 'generated_at' => '2024-05-21T14:50:00-03:00', 'owner' => 'Ana Silva', 'status' => 'done', 'icon' => 'droplet'],
        ['id' => 'REP-2005', 'name' => 'Previsão de disponibilidade hídrica', 'type' => 'planning', 'reservoir_id' => 'santa-clara', 'period' => 'Junho/2024', 'generated_at' => '2024-05-21T10:20:00-03:00', 'owner' => 'Ana Silva', 'status' => 'processing', 'icon' => 'chart-up'],
        ['id' => 'REP-2006', 'name' => 'Relatório mensal', 'type' => 'operational', 'reservoir_id' => 'santa-clara', 'period' => 'Abril/2024', 'generated_at' => '2024-05-02T09:10:00-03:00', 'owner' => 'Ana Silva', 'status' => 'scheduled', 'icon' => 'calendar'],
        ['id' => 'REP-2007', 'name' => 'Resumo diário', 'type' => 'operational', 'reservoir_id' => 'rio-verde', 'period' => '21/05/2024', 'generated_at' => '2024-05-22T07:05:00-03:00', 'owner' => 'Ana Silva', 'status' => 'done', 'icon' => 'file-text'],
        ['id' => 'REP-2008', 'name' => 'Boletim hidrológico', 'type' => 'hydrological', 'reservoir_id' => 'serra-azul', 'period' => 'Maio/2024', 'generated_at' => '2024-05-22T06:40:00-03:00', 'owner' => 'Ana Silva', 'status' => 'done', 'icon' => 'droplet'],
    ],

    'scheduled_reports' => [
        ['name' => 'Resumo diário', 'frequency' => 'Diária', 'next_run' => '2024-05-23T07:00:00-03:00'],
        ['name' => 'Boletim hidrológico', 'frequency' => 'Diária', 'next_run' => '2024-05-23T06:30:00-03:00'],
        ['name' => 'Relatório mensal', 'frequency' => 'Mensal', 'next_run' => '2024-06-01T09:00:00-03:00'],
        ['name' => 'Previsão de disponibilidade hídrica', 'frequency' => 'Mensal', 'next_run' => '2024-06-01T08:00:00-03:00'],
    ],

    /* --------------------------------- situação operacional (por represa) */
    'operation_events' => [
        'santa-clara' => [
            ['at' => '2024-05-22T09:27:00-03:00', 'component' => 'Nível', 'event' => 'Nível acima de 80%', 'priority' => 'attention', 'status' => 'new'],
            ['at' => '2024-05-22T08:47:00-03:00', 'component' => 'Comunicação', 'event' => 'Link de backup ativado', 'priority' => 'info', 'status' => 'resolved'],
            ['at' => '2024-05-22T08:32:00-03:00', 'component' => 'Comportas', 'event' => 'Teste de abertura', 'priority' => 'info', 'status' => 'resolved'],
            ['at' => '2024-05-22T08:15:00-03:00', 'component' => 'Pluviômetros', 'event' => 'Chuva moderada', 'priority' => 'info', 'status' => 'resolved'],
            ['at' => '2024-05-22T07:58:00-03:00', 'component' => 'Energia', 'event' => 'Fonte principal ativa', 'priority' => 'info', 'status' => 'resolved'],
        ],
        'rio-verde' => [
            ['at' => '2024-05-22T09:10:00-03:00', 'component' => 'Comportas', 'event' => 'Operação normal', 'priority' => 'info', 'status' => 'resolved'],
            ['at' => '2024-05-22T08:05:00-03:00', 'component' => 'Energia', 'event' => 'Fonte principal ativa', 'priority' => 'info', 'status' => 'resolved'],
        ],
        'serra-azul' => [
            ['at' => '2024-05-22T05:10:00-03:00', 'component' => 'Telemetria', 'event' => 'Sensor SEN-VAZ-22 sem comunicação', 'priority' => 'attention', 'status' => 'analysis'],
            ['at' => '2024-05-22T05:40:00-03:00', 'component' => 'Comunicação', 'event' => 'Link de backup ativado', 'priority' => 'info', 'status' => 'resolved'],
        ],
    ],

    'maintenances' => [
        'santa-clara' => [
            ['date' => '2024-05-24', 'equipment' => 'Comporta 02', 'type' => 'Preventiva', 'priority' => 'attention'],
            ['date' => '2024-05-27', 'equipment' => 'Pluviômetro 01', 'type' => 'Preventiva', 'priority' => 'low'],
            ['date' => '2024-05-30', 'equipment' => 'Gerador reserva', 'type' => 'Preventiva', 'priority' => 'attention'],
            ['date' => '2024-06-04', 'equipment' => 'Nível – Sensor 03', 'type' => 'Calibração', 'priority' => 'low'],
        ],
        'rio-verde' => [
            ['date' => '2024-05-26', 'equipment' => 'Comporta 01', 'type' => 'Preventiva', 'priority' => 'low'],
            ['date' => '2024-06-02', 'equipment' => 'Sensor de vazão', 'type' => 'Calibração', 'priority' => 'low'],
        ],
        'serra-azul' => [
            ['date' => '2024-05-25', 'equipment' => 'SEN-VAZ-22', 'type' => 'Corretiva', 'priority' => 'attention'],
            ['date' => '2024-06-06', 'equipment' => 'Pluviômetro Vale', 'type' => 'Corretiva', 'priority' => 'attention'],
        ],
    ],

    /* --------------------------------------------------------- configurações */
    'settings' => [
        'units' => [
            'level'  => 'metros (m)',
            'volume' => 'hm³',
            'flow'   => 'm³/s',
        ],
        'refresh_interval' => '5min',
        'auto_refresh'     => true,
        'indicators' => [
            ['id' => 'level',         'label' => 'Nível do reservatório', 'enabled' => true],
            ['id' => 'flow',          'label' => 'Vazão',                 'enabled' => true],
            ['id' => 'ph',            'label' => 'pH',                    'enabled' => true],
            ['id' => 'storage',       'label' => 'Volume armazenado',     'enabled' => true],
            ['id' => 'precipitation', 'label' => 'Precipitação',          'enabled' => true],
            ['id' => 'duration',      'label' => 'Duração estimada',      'enabled' => true],
        ],
        'thresholds' => [
            'level_attention_pct' => 80,
            'ph_min'              => 6.5,
            'ph_max'              => 8.5,
            'rain_critical_mm'    => 60,
        ],
        'notifications' => [
            ['id' => 'email', 'label' => 'E-mail', 'target' => 'ana.silva@hidrovale.com.br', 'enabled' => true],
            ['id' => 'panel', 'label' => 'Painel', 'target' => 'Notificações no sistema', 'enabled' => true],
        ],
    ],
];
