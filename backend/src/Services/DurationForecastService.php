<?php
/**
 * Aquapulse — service de previsão de duração da água.
 *
 * ALGORITMO DEMONSTRATIVO E ISOLADO DE PROPÓSITO.
 *
 * A projeção atual é um balanço linear simples: volume útil menos consumo
 * diário, com um ganho fixo por precipitação prevista. Serve para exercitar a
 * tela com números coerentes — NÃO é um modelo hidrológico.
 *
 * SUBSTITUIÇÃO FUTURA: a equipe de back-end/banco deve trocar apenas o corpo
 * de `project()` e `estimateDays()` por um modelo real (série histórica,
 * evaporação, sazonalidade, outorgas). A estrutura devolvida e o contrato da
 * API permanecem os mesmos, então a tela não precisa mudar.
 *
 * Usado por api/v1/monitoring/duration.php (tela "Previsão de duração da água").
 */

declare(strict_types=1);

namespace Aquapulse\Services;

use Aquapulse\Contracts\MonitoringRepositoryInterface;
use Aquapulse\Support\Clock;

final class DurationForecastService
{
    /** Cenários demonstrativos aplicados sobre o consumo médio. */
    private const SCENARIOS = [
        'saving'  => ['factor' => 0.90, 'label' => 'Cenário econômico',    'note' => 'Com economia de 10%',    'badge' => 'Recomendado'],       // consumo 10% menor
        'current' => ['factor' => 1.00, 'label' => 'Cenário atual',        'note' => 'Manutenção do consumo',  'badge' => 'Cenário base'],      // consumo igual ao atual
        'high'    => ['factor' => 1.20, 'label' => 'Cenário de alta demanda', 'note' => 'Aumento de consumo de 20%', 'badge' => 'Atenção'],     // consumo 20% maior
    ];

    private MonitoringRepositoryInterface $repo;                                                 // fonte dos dados da represa (mock ou banco)

    public function __construct(MonitoringRepositoryInterface $repo)
    {
        $this->repo = $repo;
    }

    /**
     * Estimativa de duração em dias.
     *
     * Fórmula demonstrativa: (volume útil - reserva técnica) / consumo diário.
     * TROCAR POR MODELO REAL quando o banco existir.
     *
     * @param float $usefulVolume     volume útil atual, em hm³
     * @param float $technicalReserve volume que não pode ser usado (reserva mínima), em hm³
     * @param float $dailyConsumption quanto sai por dia, em hm³/dia
     * @return int dias inteiros até atingir a reserva técnica
     */
    private function estimateDays(float $usefulVolume, float $technicalReserve, float $dailyConsumption): int
    {
        if ($dailyConsumption <= 0) {                                                            // sem consumo não há como dividir (evita divisão por zero)
            return 0;
        }
        return (int) floor(($usefulVolume - $technicalReserve) / $dailyConsumption);             // floor: arredonda para baixo (dia incompleto não conta)
    }

    /**
     * Projeção do volume ao longo do horizonte.
     *
     * Gera um ponto a cada $step dias, de 0 até $days, calculando quanto volume
     * restaria naquele dia com o consumo informado.
     *
     * @return array<int,float>
     */
    private function project(float $startVolume, float $dailyConsumption, int $days, int $step): array
    {
        $values = [];
        for ($d = 0; $d <= $days; $d += $step) {                                                 // percorre os dias do horizonte pulando de $step em $step
            // ganho demonstrativo por precipitação prevista
            $rainGain = $d * 0.35;                                                               // +0,35 hm³ por dia decorrido, fixo
            $v = $startVolume - ($dailyConsumption * $d) + $rainGain;                            // volume inicial - consumo acumulado + chuva acumulada
            $values[] = round(max(0, $v), 0);                                                    // o volume nunca fica negativo no gráfico
        }
        return $values;
    }

    /**
     * Monta todos os dados da tela de previsão para uma represa.
     *
     * Fluxo:
     *  1. busca a represa no repositório;
     *  2. calcula o horizonte em dias e o intervalo entre pontos do gráfico;
     *  3. gera os rótulos de data do eixo X;
     *  4. calcula os três cenários (econômico, atual e alta demanda);
     *  5. substitui os dias pelos valores de referência da represa;
     *  6. monta histórico, KPIs, fatores e o texto de insight.
     *
     * @param string $reservoirId represa já validada pelo endpoint
     * @param string $horizon     '30d' | '60d' | '90d' | '180d'
     * @return array<string,mixed> vazio quando a represa não existe
     */
    public function build(string $reservoirId, string $horizon): array
    {
        $r = $this->repo->reservoir($reservoirId);
        if ($r === null) {                                                                       // represa inexistente: o endpoint responde "sem dados"
            return [];
        }

        $useful    = (float) $r['useful_volume_hm3'];                                            // volume útil disponível
        $reserve   = (float) $r['technical_reserve_hm3'];                                        // reserva técnica (não pode ser consumida)
        $consumption = (float) $r['daily_consumption_hm3'];                                      // consumo médio diário
        $days      = (int) filter_var($horizon, FILTER_SANITIZE_NUMBER_INT);                     // extrai só os dígitos: "90d" -> 90
        $days      = $days > 0 ? $days : 90;                                                     // horizonte inválido cai para 90 dias
        $step      = max(1, (int) round($days / 30));                                            // ~30 pontos no gráfico: 90 dias -> ponto a cada 3 dias

        // rótulos do eixo
        $labels = [];
        $cursor = Clock::now();                                                                  // data de partida da projeção ("hoje" da aplicação)
        for ($d = 0; $d <= $days; $d += $step) {                                                 // mesmo passo usado em project(), para labels e valores alinharem
            $labels[] = Clock::shortDate($cursor->modify('+' . $d . ' days'));                   // "22 Mai", "25 Mai"... (modify em Immutable não altera $cursor)
        }

        $scenarios = [];
        foreach (self::SCENARIOS as $key => $cfg) {                                              // percorre econômico, atual e alta demanda
            $c = $consumption * $cfg['factor'];                                                  // consumo ajustado pelo fator do cenário
            $scenarios[$key] = [
                'key'    => $key,
                'label'  => $cfg['label'],
                'note'   => $cfg['note'],
                'badge'  => $cfg['badge'],
                'days'   => $this->estimateDays($useful, $reserve, $c),                          // duração calculada (sobrescrita logo abaixo)
                'values' => $this->project((float) $r['volume_hm3'], $c, $days, $step),          // curva do gráfico de projeção
            ];
        }

        // a estimativa canônica da represa é o valor de referência da tela
        $canonical = (int) $r['duration_days'];                                                  // duração "oficial" gravada nos dados da represa
        $scenarios['current']['days'] = $canonical;                                              // os dias calculados por estimateDays() são substituídos
        $scenarios['saving']['days']  = $canonical + 12;                                         // por valores fixos em relação ao de referência:
        $scenarios['high']['days']    = $canonical - 17;                                         // +12 dias no econômico, -17 na alta demanda

        $endDate = Clock::now()->modify('+' . $canonical . ' days');                             // data prevista em que a água chegaria à reserva

        // histórico das estimativas (demonstrativo)
        $history = [];
        $base = $canonical;
        $offsets = [0, -3, -5, -8, -10];                                                         // quanto cada estimativa antiga era menor que a atual
        $dates   = ['22/05/2024', '20/05/2024', '18/05/2024', '15/05/2024', '12/05/2024'];       // datas fixas das estimativas anteriores
        $conf    = [92, 91, 90, 89, 89];                                                         // confiança (%) de cada estimativa
        foreach ($dates as $i => $d) {                                                           // monta uma linha da tabela de histórico por data
            $value = $base + $offsets[$i];
            $history[] = [
                'date'       => $d,
                'estimate'   => $value,
                'variation'  => $i === 0 ? '—' : '+' . ($offsets[$i - 1] - $offsets[$i]),        // diferença para a linha anterior; a mais recente não tem variação
                'scenario'   => 'Atual',
                'confidence' => $conf[$i],
            ];
        }

        return [
            'reservoir' => [                                                                     // identificação exibida na barra de contexto
                'id'   => $r['id'],
                'name' => $r['name'],
                'code' => $r['code'],
                'telemetry_label' => 'Dados atualizados',
            ],
            'kpis' => [                                                                          // os quatro cards do topo da tela
                'duration'    => ['value' => $canonical, 'unit' => 'dias', 'note' => 'Com base no cenário atual'],
                'useful'      => ['value' => $useful, 'unit' => 'hm³', 'note' => round($useful / (float) $r['capacity_hm3'] * 100, 1) . '% da capacidade total'], // volume útil em % da capacidade
                'consumption' => ['value' => $consumption, 'unit' => 'hm³/dia', 'note' => 'Média dos últimos 7 dias'],
                'reliability' => ['value' => $r['forecast_reliability_pct'], 'unit' => '%', 'note' => 'Alta confiabilidade'],
            ],
            'projection' => [                                                                    // dados do gráfico de linhas (uma linha por cenário)
                'labels'   => $labels,
                'current'  => $scenarios['current']['values'],
                'high'     => $scenarios['high']['values'],
                'saving'   => $scenarios['saving']['values'],
                'capacity' => $r['capacity_hm3'],                                                // linha de referência: capacidade máxima
                'reserve'  => $reserve,                                                          // linha de referência: reserva técnica
                'unit'     => 'hm³',
            ],
            'estimate' => [                                                                      // card de destaque com a data estimada
                'days'      => $canonical,
                'date'      => Clock::longDate($endDate),                                        // ex.: "14 de agosto de 2024"
                'badge'     => 'Cenário estável',
                'note'      => 'Tendência de manutenção da duração',
                'max_days'  => 120,                                                              // escala do medidor visual (0 a 120 dias)
            ],
            'scenarios' => [                                                                     // cards comparativos dos três cenários
                ['key' => 'saving',  'label' => $scenarios['saving']['label'],  'days' => $scenarios['saving']['days'],  'note' => $scenarios['saving']['note'],  'badge' => $scenarios['saving']['badge'],  'status' => 'normal',    'icon' => 'leaf'],
                ['key' => 'current', 'label' => $scenarios['current']['label'], 'days' => $scenarios['current']['days'], 'note' => $scenarios['current']['note'], 'badge' => $scenarios['current']['badge'], 'status' => 'info',      'icon' => 'waves'],
                ['key' => 'high',    'label' => $scenarios['high']['label'],    'days' => $scenarios['high']['days'],    'note' => $scenarios['high']['note'],    'badge' => $scenarios['high']['badge'],    'status' => 'attention', 'icon' => 'chart-up'],
            ],
            'factors' => [                                                                       // fatores considerados na previsão (lista lateral)
                ['label' => 'Volume útil atual',        'value' => number_format($useful, 0, ',', '.') . ' hm³', 'icon' => 'waves'],       // formato brasileiro: 1.234
                ['label' => 'Vazão de entrada (média)', 'value' => '18,2 hm³/dia', 'icon' => 'chart-up'],                                 // valor fixo demonstrativo
                ['label' => 'Consumo médio',            'value' => number_format($consumption, 1, ',', '.') . ' hm³/dia', 'icon' => 'clock'],
                ['label' => 'Precipitação prevista (' . $days . 'd)', 'value' => '210 mm', 'icon' => 'cloud-rain'],                       // valor fixo demonstrativo
            ],
            'history' => $history,
            'insight' => [                                                                       // mensagem de recomendação no rodapé da tela
                'gain' => $scenarios['saving']['days'] - $canonical,                             // dias ganhos economizando 10% (hoje sempre 12)
                'text' => 'A economia de 10% pode ampliar a duração da água em até '
                          . ($scenarios['saving']['days'] - $canonical) . ' dias no horizonte selecionado.',
            ],
        ];
    }
}
