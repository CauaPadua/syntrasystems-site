<?php
/**
 * Aquapulse — gerador das séries históricas do banco (ferramenta de carga).
 *
 * É a peça que MIGRA os dados simulados para o MySQL: backend/storage/mock/monitoring.php
 * guarda apenas os valores ATUAIS de cada represa, enquanto o banco precisa do
 * histórico que alimenta gráficos e tabelas. Este arquivo reconstrói esse
 * histórico a partir dos mesmos âncoras, usando exatamente o algoritmo que o
 * MockMonitoringRepository usava em memória (onda senoidal com semente fixa
 * derivada do ID da represa + métrica).
 *
 * DETERMINÍSTICO: sem rand() e sem time(). Rodar duas vezes gera exatamente as
 * mesmas linhas, e o seed pode ser reexecutado sem duplicar nada (as tabelas
 * têm chave única por série + instante).
 *
 * Duas resoluções são gravadas (coluna readings.granularity):
 *   'hour' — últimas 48 horas, para o gráfico de 24 h e as tabelas de leituras;
 *   'day'  — últimos ~13 meses, para os gráficos de 7, 30 e 90 dias e o de 12 meses.
 *
 * Quem usa: backend/database/migrate.php (comandos seed e install).
 * Não é carregado pela aplicação em nenhuma requisição.
 */

declare(strict_types=1);

final class SeriesGenerator
{
    /** Quantas horas de leituras horárias manter (48 h = gráfico de 24 h + folga). */
    public const HORAS = 48;

    /** Quantos dias de leituras diárias manter (~13 meses, para o gráfico de 12 meses). */
    public const DIAS = 400;

    /**
     * Parâmetros de oscilação por métrica, copiados do MockMonitoringRepository
     * para que as curvas continuem com a mesma aparência.
     *
     * campo     = chave do valor atual no arquivo simulado (o "âncora", último ponto da série)
     * amplitude = tamanho da oscilação
     * deriva    = quanto a série sobe do primeiro ao último ponto
     * casas     = casas decimais do valor gravado
     */
    private const METRICAS = [
        'level'      => ['campo' => 'level_pct',    'amplitude' => 1.4,  'deriva' => 2.2,   'casas' => 1],
        'cota'       => ['campo' => 'cota_m',       'amplitude' => 0.9,  'deriva' => 2.4,   'casas' => 1],
        'flow'       => ['campo' => 'flow_m3s',     'amplitude' => 3.2,  'deriva' => 9.5,   'casas' => 1],
        'inflow'     => ['campo' => 'inflow_m3s',   'amplitude' => 5.0,  'deriva' => 4.2,   'casas' => 1],
        'outflow'    => ['campo' => 'outflow_m3s',  'amplitude' => 3.6,  'deriva' => 2.2,   'casas' => 1],
        'ph'         => ['campo' => 'ph',           'amplitude' => 0.14, 'deriva' => 0.1,   'casas' => 2],
        'storage'    => ['campo' => 'volume_hm3',   'amplitude' => 18.0, 'deriva' => 260.0, 'casas' => 0],
        'water_temp' => ['campo' => 'water_temp_c', 'amplitude' => 0.6,  'deriva' => 0.4,   'casas' => 1],
        // 'precipitation' nao entra aqui: chuva nao tem tendencia nem valor
        // "arrastado" de um ponto ao outro; e tratada em serieChuva().
    ];

    /**
     * Semente determinística de cada série (mesma fórmula do repositório simulado).
     * crc32 devolve sempre o mesmo inteiro para o mesmo texto.
     */
    private static function semente(string $chave): float
    {
        return (crc32($chave) % 1000) / 1000.0;
    }

    /**
     * Série com tendência + oscilação, terminando exatamente no valor âncora.
     *
     * @param int   $quantidade pontos a gerar
     * @param float $fim        valor do último ponto (o valor atual do indicador)
     * @param float $amplitude  tamanho da oscilação
     * @param float $deriva     diferença entre o primeiro e o último ponto
     * @param float $semente    deslocamento da onda (0 a 0,999)
     * @param int   $casas      casas decimais
     * @return array<int,float> do mais antigo para o mais recente
     */
    public static function onda(int $quantidade, float $fim, float $amplitude, float $deriva, float $semente, int $casas): array
    {
        $valores = [];
        $inicio = $fim - $deriva;

        for ($i = 0; $i < $quantidade; $i++) {
            $t = $quantidade > 1 ? $i / ($quantidade - 1) : 1.0;      // 0 no primeiro ponto, 1 no último
            $base = $inicio + ($fim - $inicio) * $t;                  // tendência linear

            $osc = sin(($i * 0.9) + $semente * 6.283) * $amplitude    // 6,283 ~ 2*PI: a semente desloca a onda uma volta inteira
                 + sin(($i * 0.37) + $semente * 3.14) * ($amplitude * 0.45);

            // A oscilação precisa sumir no último ponto (para o valor final ser
            // exatamente o indicador atual), mas só no finzinho da série: com um
            // decaimento suave ao longo de todo o histórico, os últimos dias
            // ficariam sem variação nenhuma e os gráficos de 7 e 30 dias
            // apareceriam como uma linha reta. O expoente alto concentra o
            // decaimento nos ~4% finais dos pontos.
            $osc *= 1 - pow($t, 60);

            $valores[] = round($base + $osc, $casas);
        }

        $valores[$quantidade - 1] = round($fim, $casas);              // garante o último ponto exato
        return $valores;
    }

    /**
     * Chuva diária: valores independentes e nunca negativos (não há tendência).
     * O último dia recebe exatamente o acumulado de 24 h do arquivo simulado.
     *
     * @return array<int,float> um total por dia, do mais antigo para o mais recente
     */
    public static function serieChuva(int $quantidade, float $ancora, float $semente): array
    {
        $valores = [];
        for ($i = 0; $i < $quantidade; $i++) {
            $valores[] = round(abs(sin(($i * 1.7) + $semente * 6.283)) * ($ancora * 0.85) + 2.0, 1);
        }
        $valores[$quantidade - 1] = round($ancora, 1);
        return $valores;
    }

    /**
     * Distribui o total de chuva de um dia entre 24 horas.
     *
     * Os pesos vêm de uma senoide (chove mais em algumas horas), somam 1 e são
     * multiplicados pelo total do dia: a soma das 24 horas devolve o total
     * original, então o acumulado de 24 h calculado pelo banco (SUM) bate com o
     * valor de referência.
     *
     * @return array<int,float> 24 valores, da hora mais antiga para a mais recente
     */
    public static function distribuirChuva(float $totalDoDia, float $semente): array
    {
        $pesos = [];
        $soma = 0.0;
        for ($h = 0; $h < 24; $h++) {
            $p = abs(sin(($h * 0.7) + $semente * 6.283)) + 0.05;      // +0,05 evita hora com peso zero
            $pesos[] = $p;
            $soma += $p;
        }

        $valores = [];
        foreach ($pesos as $p) {
            $valores[] = round($totalDoDia * ($p / $soma), 2);
        }
        return $valores;
    }

    /**
     * Monta todas as leituras de uma represa (séries diárias e horárias).
     *
     * @param array<string,mixed> $represa linha do arquivo simulado (âncoras)
     * @param DateTimeImmutable   $agora   instante final da série (relógio da aplicação)
     * @return array<int,array{0:string,1:string,2:string,3:float,4:string}>
     *         linhas prontas: [reservoir_id, metric, granularity, value, recorded_at]
     */
    public static function leiturasDaRepresa(array $represa, DateTimeImmutable $agora): array
    {
        $id = (string) $represa['id'];
        $linhas = [];

        foreach (self::METRICAS as $metrica => $cfg) {
            $ancora = (float) $represa[$cfg['campo']];
            $semente = self::semente($id . '|' . $metrica);

            // --- série diária (do dia mais antigo até hoje)
            $diarios = self::onda(self::DIAS + 1, $ancora, $cfg['amplitude'], $cfg['deriva'], $semente, $cfg['casas']);
            foreach ($diarios as $i => $valor) {
                $quando = $agora->modify('-' . (self::DIAS - $i) . ' days');
                $linhas[] = [$id, $metrica, 'day', $valor, $quando->format('Y-m-d H:i:s')];
            }

            // --- série horária (últimas 48 h), com a mesma âncora no fim.
            // Amplitude e deriva menores: em poucas horas o indicador varia menos
            // do que ao longo de um ano.
            $horarios = self::onda(self::HORAS + 1, $ancora, $cfg['amplitude'] * 0.6, $cfg['deriva'] * 0.15, $semente, $cfg['casas']);
            foreach ($horarios as $i => $valor) {
                $quando = $agora->modify('-' . (self::HORAS - $i) . ' hours');
                $linhas[] = [$id, $metrica, 'hour', $valor, $quando->format('Y-m-d H:i:s')];
            }
        }

        // --- chuva: totais diários e a distribuição deles por hora
        $ancoraChuva = (float) $represa['rain_24h_mm'];
        $sementeChuva = self::semente($id . '|precipitation');
        $chuvaDiaria = self::serieChuva(self::DIAS + 1, $ancoraChuva, $sementeChuva);

        foreach ($chuvaDiaria as $i => $valor) {
            $quando = $agora->modify('-' . (self::DIAS - $i) . ' days');
            $linhas[] = [$id, 'precipitation', 'day', $valor, $quando->format('Y-m-d H:i:s')];
        }

        // Chuva horária das últimas 48 h: cada janela de 24 h recebe o total do
        // dia correspondente, distribuído entre as horas. Como a soma da
        // distribuição devolve o total, as últimas 24 horas somam exatamente o
        // acumulado atual da represa — que é como o banco calcula rain_24h_mm.
        $distribuicoes = [];                                          // índice 0 = janela atual, 1 = 24 h antes, 2 = 48 h antes
        for ($janela = 0; $janela <= 2; $janela++) {
            $totalDoDia = $chuvaDiaria[self::DIAS - $janela];
            $distribuicoes[$janela] = self::distribuirChuva($totalDoDia, $sementeChuva);
        }

        for ($i = 0; $i <= self::HORAS; $i++) {
            $quando = $agora->modify('-' . (self::HORAS - $i) . ' hours');
            $posicao = self::HORAS - $i;                              // 0 = hora atual, 23 = 23 h atrás, 48 = 48 h atrás
            $janela = intdiv($posicao, 24);                           // a que bloco de 24 h essa hora pertence
            $hora = 23 - ($posicao % 24);                             // posição dentro do bloco (23 = mais recente)
            $linhas[] = [$id, 'precipitation', 'hour', $distribuicoes[$janela][$hora], $quando->format('Y-m-d H:i:s')];
        }

        return $linhas;
    }

    /**
     * Medições de um ponto de coleta de pH (campanhas do laboratório).
     *
     * @return array<int,array{0:int,1:float,2:float,3:string}> [ph_point_id, ph, temperatura, recorded_at]
     */
    public static function leiturasDoPontoPh(int $pontoId, string $chaveSemente, float $ancoraPh, float $ancoraTemp, DateTimeImmutable $agora): array
    {
        $semente = self::semente($chaveSemente);
        $linhas = [];

        $phs = self::onda(self::HORAS + 1, $ancoraPh, 0.12, 0.08, $semente, 2);
        $temps = self::onda(self::HORAS + 1, $ancoraTemp, 0.5, 0.3, $semente, 1);

        foreach ($phs as $i => $ph) {
            $quando = $agora->modify('-' . (self::HORAS - $i) . ' hours');
            $linhas[] = [$pontoId, $ph, $temps[$i], $quando->format('Y-m-d H:i:s')];
        }
        return $linhas;
    }

    /**
     * Medições horárias de uma estação pluviométrica (48 h).
     * As últimas 24 h somam o acumulado exibido para a estação.
     *
     * @return array<int,array{0:string,1:float,2:string}> [station_id, rain_mm, recorded_at]
     */
    public static function leiturasDaEstacao(string $estacaoId, float $acumulado24h, DateTimeImmutable $agora): array
    {
        $semente = self::semente('estacao|' . $estacaoId);

        // Uma distribuição por bloco de 24 h: a janela atual usa o acumulado da
        // estação, e os blocos anteriores ficam progressivamente mais secos.
        $distribuicoes = [];
        for ($janela = 0; $janela <= 2; $janela++) {
            $total = round($acumulado24h * (1.0 - 0.28 * $janela), 1);
            $distribuicoes[$janela] = self::distribuirChuva(max($total, 0.0), $semente);
        }

        $linhas = [];
        for ($i = 0; $i <= self::HORAS; $i++) {
            $quando = $agora->modify('-' . (self::HORAS - $i) . ' hours');
            $posicao = self::HORAS - $i;
            $janela = intdiv($posicao, 24);
            $hora = 23 - ($posicao % 24);
            $linhas[] = [$estacaoId, $distribuicoes[$janela][$hora], $quando->format('Y-m-d H:i:s')];
        }
        return $linhas;
    }
}
