<?php
/**
 * Aquapulse — geração de relatórios.
 *
 * Um relatório é a exportação, em CSV, das leituras de uma represa em um
 * período, consolidadas por dia. generate() registra o pedido na tabela
 * reports; csv() monta o arquivo a partir das leituras gravadas — o conteúdo
 * é recalculado a cada download, sempre do banco.
 */

declare(strict_types=1);

namespace Aquapulse\Services;

use Aquapulse\Support\ApiException;
use Aquapulse\Support\Clock;
use DateTimeImmutable;
use PDO;

final class ReportService
{
    /** Tipos de relatório e as grandezas que cada um exporta. */
    public const TYPES = [
        'operational'  => ['label' => 'Operacional', 'name' => 'Leituras diárias — todas as grandezas', 'icon' => 'file-text',
                           'metrics' => ['level', 'cota', 'storage', 'inflow', 'outflow', 'ph', 'water_temp', 'precipitation']],
        'hydrological' => ['label' => 'Hidrológico', 'name' => 'Leituras diárias — nível, volume, vazão e chuva', 'icon' => 'waves',
                           'metrics' => ['level', 'cota', 'storage', 'inflow', 'outflow', 'precipitation']],
        'quality'      => ['label' => 'Qualidade', 'name' => 'Leituras diárias — pH e temperatura', 'icon' => 'droplet',
                           'metrics' => ['ph', 'water_temp']],
    ];

    /** Cabeçalho de cada coluna do CSV. */
    private const COLUNAS = [
        'level'         => 'Nível (%)',
        'cota'          => 'Cota (m)',
        'storage'       => 'Volume (hm³)',
        'inflow'        => 'Afluência (m³/s)',
        'outflow'       => 'Defluência (m³/s)',
        'ph'            => 'pH',
        'water_temp'    => 'Temperatura da água (°C)',
        'precipitation' => 'Chuva (mm)',
    ];

    private const PERIODO_MAXIMO = 366;                       // dias

    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Registra um relatório e devolve o código gerado (ex.: REP-2001).
     *
     * @param string $from data inicial (AAAA-MM-DD)
     * @param string $to   data final (AAAA-MM-DD)
     * @param array{id:int,name:string} $user
     */
    public function generate(string $reservoirId, string $type, string $from, string $to, array $user): string
    {
        $erros = [];

        if (!isset(self::TYPES[$type])) {
            $erros['type'] = 'Selecione o tipo de relatório.';
        }

        $inicio = DateTimeImmutable::createFromFormat('!Y-m-d', $from, Clock::timezone());
        $fim = DateTimeImmutable::createFromFormat('!Y-m-d', $to, Clock::timezone());

        if ($inicio === false) {
            $erros['from'] = 'Informe a data inicial.';
        }
        if ($fim === false) {
            $erros['to'] = 'Informe a data final.';
        }
        if ($inicio !== false && $fim !== false) {
            if ($fim < $inicio) {
                $erros['to'] = 'A data final não pode ser anterior à inicial.';
            } elseif ($fim > Clock::now()) {
                $erros['to'] = 'A data final não pode estar no futuro.';
            } elseif ($inicio->diff($fim)->days >= self::PERIODO_MAXIMO) {
                $erros['from'] = 'O período máximo é de um ano.';
            }
        }

        if ($erros !== []) {
            throw new ApiException('VALIDATION_ERROR', 'Verifique os campos informados.', 422, $erros);
        }

        $numero = (int) $this->pdo->query(
            "SELECT COALESCE(MAX(CAST(SUBSTRING(id, 5) AS UNSIGNED)), 2000) + 1 FROM reports WHERE id LIKE 'REP-%'"
        )->fetchColumn();
        $id = 'REP-' . $numero;
        $cfg = self::TYPES[$type];

        $stmt = $this->pdo->prepare(
            'INSERT INTO reports (id, reservoir_id, name, type, period_label, period_start, period_end,
                                  generated_at, owner, icon)
             VALUES (:id, :rid, :name, :type, :label, :inicio, :fim, :quando, :owner, :icon)'
        );
        $stmt->execute([
            'id'     => $id,
            'rid'    => $reservoirId,
            'name'   => $cfg['name'],
            'type'   => $type,
            'label'  => $inicio->format('d/m/Y') . ' – ' . $fim->format('d/m/Y'),
            'inicio' => $inicio->format('Y-m-d'),
            'fim'    => $fim->format('Y-m-d'),
            'quando' => Clock::sql(),
            'owner'  => $user['name'],
            'icon'   => $cfg['icon'],
        ]);

        return $id;
    }

    /**
     * Conteúdo do arquivo CSV de um relatório.
     *
     * @param array<string,mixed> $report    linha de MonitoringRepositoryInterface::reports()
     * @param string              $reservoir nome da represa, para o cabeçalho do arquivo
     */
    public function csv(array $report, string $reservoir): string
    {
        $metricas = self::TYPES[$report['type']]['metrics'] ?? self::TYPES['operational']['metrics'];

        // Uma linha por dia e grandeza: média do dia (para chuva, o total).
        $nomes = [];
        $params = ['rid' => $report['reservoir_id'], 'inicio' => $report['period_start'] . ' 00:00:00', 'fim' => $report['period_end'] . ' 23:59:59'];
        foreach ($metricas as $i => $m) {
            $nomes[] = ':m' . $i;
            $params['m' . $i] = $m;
        }

        $stmt = $this->pdo->prepare(
            "SELECT DATE(recorded_at) AS dia, metric,
                    CASE WHEN metric = 'precipitation' THEN SUM(value) ELSE AVG(value) END AS valor
               FROM readings
              WHERE reservoir_id = :rid
                AND recorded_at BETWEEN :inicio AND :fim
                AND metric IN (" . implode(', ', $nomes) . ')
              GROUP BY dia, metric
              ORDER BY dia, metric'
        );
        $stmt->execute($params);

        $porDia = [];
        foreach ($stmt->fetchAll() as $l) {
            $porDia[(string) $l['dia']][(string) $l['metric']] = (float) $l['valor'];
        }

        // Monta o arquivo em memória. ";" como separador e vírgula decimal: o
        // formato que o Excel em português abre direto.
        $arquivo = fopen('php://temp', 'r+');
        $linha = static function (array $campos) use ($arquivo): void {
            fputcsv($arquivo, $campos, ';');
        };

        $linha(['Aquapulse — ' . $report['name']]);
        $linha(['Represa', $reservoir]);
        $linha(['Período', $report['period']]);
        $linha(['Gerado em', Clock::dateTime(Clock::parse($report['generated_at'])), 'por ' . $report['owner']]);
        $linha([]);
        $linha(array_merge(['Data'], array_map(static fn (string $m): string => self::COLUNAS[$m], $metricas)));

        foreach ($porDia as $dia => $valores) {
            $campos = [Clock::parse($dia)->format('d/m/Y')];
            foreach ($metricas as $m) {
                $casas = EquipmentRules::TYPES[$m]['decimals'] ?? 1;
                $campos[] = isset($valores[$m]) ? number_format($valores[$m], $casas, ',', '') : '';
            }
            $linha($campos);
        }

        if ($porDia === []) {
            $linha(['Não há leituras registradas neste período.']);
        }

        rewind($arquivo);
        $conteudo = (string) stream_get_contents($arquivo);
        fclose($arquivo);

        return "\xEF\xBB\xBF" . $conteudo;                    // BOM: faz o Excel reconhecer os acentos (UTF-8)
    }
}
