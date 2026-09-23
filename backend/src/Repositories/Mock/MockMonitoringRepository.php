<?php
/**
 * Aquapulse — implementação SIMULADA do repositório de monitoramento.
 *
 * Lê backend/storage/mock/monitoring.php. Não escreve nem persiste nada: é a
 * origem usada quando o sistema roda SEM banco de dados configurado.
 *
 * DETERMINISMO: as séries são geradas por uma função senoidal com semente fixa
 * derivada do ID da represa e da métrica. Nunca usa rand()/time(). O ÚLTIMO
 * ponto de toda série é forçado ao valor atual do KPI, garantindo que gráfico
 * e card nunca se contradigam.
 *
 * SITUAÇÃO: o PdoMonitoringRepository já existe e lê o MySQL. Esta versão
 * simulada continua sendo a origem usada quando não há banco configurado
 * (sem DB_HOST no backend/.env) — desenvolvimento e demonstração offline.
 *
 * É a implementação usada hoje sempre que não existe DB_HOST (Support\Container).
 * O arquivo de dados guarda só os valores "atuais" de cada represa; o histórico
 * dos gráficos e as últimas leituras são FABRICADOS aqui, a partir desses valores.
 */

declare(strict_types=1);

namespace Aquapulse\Repositories\Mock;

use Aquapulse\Contracts\MonitoringRepositoryInterface;
use Aquapulse\Support\Clock;

final class MockMonitoringRepository implements MonitoringRepositoryInterface
{
    /** @var array<string,mixed>|null */
    private ?array $data = null;                                                   // conteúdo do arquivo simulado em memória; null até a primeira leitura

    private string $file;                                                          // caminho do arquivo de dados

    /** @param string|null $file permite usar outro arquivo de dados; por padrão, storage/mock/monitoring.php */
    public function __construct(?string $file = null)
    {
        $this->file = $file ?? AQ_BACKEND_PATH . '/storage/mock/monitoring.php';
    }

    /** Carrega o arquivo simulado uma única vez por requisição. */
    private function db(): array
    {
        if ($this->data === null) {
            $this->data = is_file($this->file) ? (array) require $this->file : []; // o arquivo faz "return [...]"; se não existir, tudo fica vazio
        }
        return $this->data;
    }

    /* ------------------------------------------------------------ empresas */

    /** Lista de empresas do arquivo simulado. */
    public function companies(): array
    {
        return $this->db()['companies'] ?? [];                                     // "?? []" evita erro se a chave não existir
    }

    /* ------------------------------------------------------------ represas */

    /** Represas de todas as empresas, ou só da empresa informada. */
    public function reservoirs(string $companyId = 'all'): array
    {
        $all = $this->db()['reservoirs'] ?? [];

        if ($companyId === 'all') {
            return $all;
        }

        return array_values(array_filter(                                          // equivalente em memória a "WHERE company_id = ?"
            $all,
            static fn (array $r): bool => $r['company_id'] === $companyId
        ));                                                                        // array_values reindexa (0, 1, 2...) para virar lista no JSON
    }

    /** Uma represa pelo ID, ou null. */
    public function reservoir(string $reservoirId): ?array
    {
        foreach ($this->db()['reservoirs'] ?? [] as $r) {                          // busca linear: são poucas represas
            if ($r['id'] === $reservoirId) {
                return $r;
            }
        }
        return null;
    }

    /* -------------------------------------------------------------- séries */

    /**
     * Semente determinística: mesma represa + métrica sempre produz a mesma
     * variação. Deriva de crc32 para não depender de rand().
     *
     * @return float número entre 0 e 0,999 usado para deslocar a onda de cada série
     */
    private function seed(string $reservoirId, string $metric): float
    {
        return (crc32($reservoirId . '|' . $metric) % 1000) / 1000.0;              // crc32 gera um inteiro fixo para o texto; "% 1000 / 1000" o reduz a 0.000–0.999
    }

    /**
     * Gera pontos oscilando em torno de uma base e termina exatamente no
     * valor final informado.
     *
     * @param int   $count     quantidade de pontos (depende do período)
     * @param float $end       valor do último ponto (o valor atual do KPI)
     * @param float $amplitude tamanho da oscilação para cima e para baixo
     * @param float $drift     quanto a série sobe do primeiro ao último ponto
     * @param float $seed      deslocamento da onda (seed())
     * @param int   $decimals  casas decimais de cada ponto
     * @return array<int,float>
     */
    private function wave(int $count, float $end, float $amplitude, float $drift, float $seed, int $decimals = 1): array
    {
        $values = [];
        $start = $end - $drift;                                                    // a série começa "drift" unidades abaixo do valor final

        for ($i = 0; $i < $count; $i++) {                                          // gera um ponto por posição do eixo X
            $t = $count > 1 ? $i / ($count - 1) : 1.0;                             // posição normalizada: 0 no primeiro ponto, 1 no último

            // tendência linear do início até o fim
            $base = $start + ($end - $start) * $t;

            // oscilação suave e reprodutível
            $osc = sin(($i * 0.9) + $seed * 6.283) * $amplitude                    // onda principal (6,283 ≈ 2π: a semente desloca a onda por uma volta inteira)
                 + sin(($i * 0.37) + $seed * 3.14) * ($amplitude * 0.45);          // segunda onda mais lenta e menor, para não parecer um seno perfeito

            // a oscilação desaparece no último ponto para cravar o valor atual
            $osc *= (1 - $t * $t);                                                 // fator 1 no início e 0 no fim: a oscilação some gradualmente

            $values[] = round($base + $osc, $decimals);
        }

        // garante que o último ponto é exatamente o KPI atual
        $values[$count - 1] = round($end, $decimals);

        return $values;
    }

    /**
     * Rótulos do eixo X conforme o período.
     *
     * @param int $count recebido por REFERÊNCIA (&): o método grava nele quantos
     *                   pontos o período tem, e series() usa esse número
     * @return array<int,string>
     */
    private function labels(string $period, int &$count): array
    {
        $tz = Clock::timezone();
        $now = Clock::now();                                                       // "agora" demonstrativo: 22/05/2024 09:30
        $labels = [];

        switch ($period) {
            case '24h':                                                            // 24 pontos, um por hora, terminando na hora atual
                $count = 24;
                $cursor = (clone $now)->modify('-23 hours');
                for ($i = 0; $i < $count; $i++) {
                    $labels[] = $cursor->format('H:i');                            // "10:30", "11:30"...
                    $cursor = $cursor->modify('+1 hour');
                }
                break;

            case '30d':                                                            // 30 pontos diários, terminando hoje
                $count = 30;
                $cursor = (clone $now)->modify('-29 days');
                for ($i = 0; $i < $count; $i++) {
                    $labels[] = Clock::shortDate($cursor);                         // "23 Abr", "24 Abr"...
                    $cursor = $cursor->modify('+1 day');
                }
                break;

            case '90d':                                                            // 91 pontos diários a partir de HOJE para frente (datas futuras)
                $count = 91;
                $cursor = clone $now;
                for ($i = 0; $i < $count; $i++) {
                    $labels[] = Clock::shortDate($cursor);
                    $cursor = $cursor->modify('+1 day');
                }
                break;

            case '12m':                                                            // 12 pontos mensais, terminando no mês atual
                $count = 12;
                $cursor = (clone $now)->modify('first day of -11 months');         // "first day of" evita pular meses curtos (ex.: 31 de janeiro + 1 mês)
                for ($i = 0; $i < $count; $i++) {
                    $labels[] = Clock::shortMonth($cursor);                        // "Jun", "Jul"...
                    $cursor = $cursor->modify('+1 month');
                }
                break;

            case '7d':
            default:                                                               // padrão: 7 pontos diários, terminando hoje
                $count = 7;
                $cursor = (clone $now)->modify('-6 days');
                for ($i = 0; $i < $count; $i++) {
                    $labels[] = Clock::shortDate($cursor);
                    $cursor = $cursor->modify('+1 day');
                }
                break;
        }

        unset($tz);                                                                // $tz não é usado; é descartado aqui
        return $labels;
    }

    /**
     * Série temporal simulada de uma métrica.
     *
     * Cada métrica usa como "âncora" o valor atual da represa e parâmetros
     * próprios de oscilação: o pH oscila pouco (0,14), o volume oscila muito (18 hm³).
     */
    public function series(string $reservoirId, string $metric, string $period): array
    {
        $r = $this->reservoir($reservoirId);
        if ($r === null) {                                                         // represa inexistente: série vazia
            return ['labels' => [], 'values' => []];
        }

        $count = 7;                                                                // valor inicial; labels() sobrescreve pela referência
        $labels = $this->labels($period, $count);
        $seed = $this->seed($reservoirId, $metric);

        // âncora final + amplitude + deriva por métrica
        switch ($metric) {                                                         // wave(qtd, valor final, amplitude, deriva, semente, casas)
            case 'level':                                                          // nível em %
                $values = $this->wave($count, (float) $r['level_pct'], 1.4, 2.2, $seed, 1);
                break;
            case 'cota':                                                           // cota em metros
                $values = $this->wave($count, (float) $r['cota_m'], 0.9, 2.4, $seed, 1);
                break;
            case 'flow':                                                           // vazão em m³/s
                $values = $this->wave($count, (float) $r['flow_m3s'], 3.2, 9.5, $seed, 1);
                break;
            case 'inflow':                                                         // afluência (entrada)
                $values = $this->wave($count, (float) $r['inflow_m3s'], 5.0, 4.2, $seed, 1);
                break;
            case 'outflow':                                                        // defluência (saída)
                $values = $this->wave($count, (float) $r['outflow_m3s'], 3.6, 2.2, $seed, 1);
                break;
            case 'ph':                                                             // pH varia pouco e usa 2 casas decimais
                $values = $this->wave($count, (float) $r['ph'], 0.14, 0.1, $seed, 2);
                break;
            case 'storage':                                                        // volume em hm³, sem casas decimais
                $values = $this->wave($count, (float) $r['volume_hm3'], 18.0, 260.0, $seed, 0);
                break;
            case 'precipitation':
                // chuva não tem tendência: valores independentes e não negativos
                $values = [];
                for ($i = 0; $i < $count; $i++) {
                    $v = abs(sin(($i * 1.7) + $seed * 6.283)) * ((float) $r['rain_24h_mm'] * 0.85) + 2.0; // abs() impede chuva negativa; +2 mm de mínimo
                    $values[] = round($v, 1);
                }
                $values[$count - 1] = (float) $r['rain_24h_mm'];                   // último ponto = chuva das últimas 24 h
                break;
            default:                                                               // métrica desconhecida: zeros, para o gráfico não quebrar
                $values = array_fill(0, $count, 0.0);
        }

        return ['labels' => $labels, 'values' => $values];
    }

    /* ------------------------------------------------------------ leituras */

    /**
     * Últimas leituras simuladas, da mais recente para a mais antiga.
     * Cada linha tem colunas diferentes conforme a métrica (a tabela de cada tela é diferente).
     */
    public function readings(string $reservoirId, string $metric, int $limit = 5): array
    {
        $r = $this->reservoir($reservoirId);
        if ($r === null) {
            return [];
        }

        $rows = [];
        $cursor = Clock::now();

        for ($i = 0; $i < $limit; $i++) {                                          // uma linha por leitura
            $when = (clone $cursor)->modify('-' . ($i * 15) . ' minutes');         // leituras a cada 15 minutos, voltando no tempo
            $step = $i * 0.1;                                                      // pequena diferença crescente em relação ao valor atual

            switch ($metric) {
                case 'flow':
                    $rows[] = [
                        'at'      => Clock::iso($when),                            // data em formato de máquina
                        'time'    => Clock::dateTime($when),                       // data para exibição
                        'inflow'  => round((float) $r['inflow_m3s'] - $step * 4, 1),
                        'outflow' => round((float) $r['outflow_m3s'] - $step * 3, 1),
                        'balance' => round(((float) $r['inflow_m3s'] - $step * 4) - ((float) $r['outflow_m3s'] - $step * 3), 1), // entrada - saída da mesma linha
                        'status'  => 'online',
                    ];
                    break;

                case 'level':
                    $rows[] = [
                        'at'        => Clock::iso($when),
                        'time'      => Clock::dateTime($when),
                        'cota'      => round((float) $r['cota_m'] - $step * 3, 1),
                        'level'     => round((float) $r['level_pct'] - $step * 3, 1),
                        'variation' => round((float) $r['cota_variation_m'] - $step, 1),
                        // a classificação fica no MonitoringService, não no repositório
                    ];
                    break;

                case 'ph':
                    $rows[] = [
                        'at'     => Clock::iso($when),
                        'time'   => Clock::dateTime($when),
                        'ph'     => round((float) $r['ph'] - $step * 0.5, 1),
                        'temp'   => round((float) $r['water_temp_c'] + $step * 0.5, 1), // temperatura da água (°C)
                        'point'  => ['Entrada principal', 'Centro do reservatório', 'Próximo à barragem'][$i % 3], // alterna entre os 3 pontos de coleta
                        'status' => 'normal',
                    ];
                    break;

                case 'storage':                                                    // volume é registrado por DIA, não a cada 15 min
                    $day = (clone Clock::now())->modify('-' . $i . ' days');
                    $rows[] = [
                        'at'        => Clock::iso($day),
                        'date'      => Clock::date($day),
                        'volume'    => round((float) $r['volume_hm3'] - $i * 12, 0),   // 12 hm³ a menos por dia para trás
                        'occupancy' => round((float) $r['level_pct'] - $i * 0.9, 1),
                        'variation' => 12 + $i,
                        'status'    => 'normal',
                    ];
                    break;

                default:                                                           // métrica sem tabela própria
                    $rows[] = [
                        'at'     => Clock::iso($when),
                        'time'   => Clock::dateTime($when),
                        'value'  => 0,
                        'status' => 'normal',
                    ];
            }
        }

        return $rows;
    }

    /* ---------------------------------------------------- listas auxiliares */
    /* No arquivo de dados estas listas são agrupadas pelo ID da represa:
       'sensors' => ['santa-clara' => [...], 'rio-verde' => [...]]. */

    public function sensors(string $reservoirId): array
    {
        return $this->db()['sensors'][$reservoirId] ?? [];                         // represa sem sensores cadastrados = lista vazia
    }

    public function phPoints(string $reservoirId): array
    {
        return $this->db()['ph_points'][$reservoirId] ?? [];
    }

    public function rainStations(string $reservoirId): array
    {
        return $this->db()['rain_stations'][$reservoirId] ?? [];
    }

    public function operationEvents(string $reservoirId): array
    {
        return $this->db()['operation_events'][$reservoirId] ?? [];
    }

    public function maintenances(string $reservoirId): array
    {
        return $this->db()['maintenances'][$reservoirId] ?? [];
    }

    public function scheduledReports(): array
    {
        return $this->db()['scheduled_reports'] ?? [];                             // não depende de represa
    }

    public function settings(): array
    {
        return $this->db()['settings'] ?? [];
    }

    /* ------------------------------------------------------------ filtros */

    /**
     * Alertas filtrados em memória. Cada filtro diferente de 'all' elimina os
     * alertas que não batem; um alerta só permanece se passar pelos três.
     */
    public function alerts(string $reservoirId = 'all', string $severity = 'all', string $status = 'all'): array
    {
        $rows = $this->db()['alerts'] ?? [];

        return array_values(array_filter($rows, static function (array $a) use ($reservoirId, $severity, $status): bool { // "use" traz os filtros para dentro da função anônima
            if ($reservoirId !== 'all' && $a['reservoir_id'] !== $reservoirId) {   // filtro por represa
                return false;
            }
            if ($severity !== 'all' && $a['severity'] !== $severity) {             // filtro por gravidade
                return false;
            }
            if ($status !== 'all' && $a['status'] !== $status) {                   // filtro por andamento
                return false;
            }
            return true;                                                           // passou por todos os filtros ativos
        }));
    }

    /** Relatórios filtrados em memória (mesma lógica de alerts()). */
    public function reports(string $reservoirId = 'all', string $type = 'all', string $status = 'all'): array
    {
        $rows = $this->db()['reports'] ?? [];

        return array_values(array_filter($rows, static function (array $r) use ($reservoirId, $type, $status): bool {
            if ($reservoirId !== 'all' && $r['reservoir_id'] !== $reservoirId) {
                return false;
            }
            if ($type !== 'all' && $r['type'] !== $type) {                         // filtro por tipo de relatório
                return false;
            }
            if ($status !== 'all' && $r['status'] !== $status) {                   // filtro por situação do relatório
                return false;
            }
            return true;
        }));
    }
}
