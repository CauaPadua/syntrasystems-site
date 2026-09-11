<?php
declare(strict_types=1);

namespace Aquapulse\Repositories;

use Aquapulse\Contracts\MonitoringRepositoryInterface;
use PDO;

final class PdoMonitoringRepository implements MonitoringRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function companies(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM companies');
        return $stmt->fetchAll();
    }

    public function reservoirs(string $companyId = 'all'): array
    {
        if ($companyId === 'all') {
            $stmt = $this->pdo->query('SELECT * FROM reservoirs');
            return $stmt->fetchAll();
        }
        $stmt = $this->pdo->prepare('SELECT * FROM reservoirs WHERE company_id = :cid');
        $stmt->execute(['cid' => $companyId]);
        return $stmt->fetchAll();
    }

    public function reservoir(string $reservoirId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM reservoirs WHERE id = :id');
        $stmt->execute(['id' => $reservoirId]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function series(string $reservoirId, string $metric, string $period): array
    {
        $intervalo = match ($period) {
            '24h' => 'INTERVAL 24 HOUR',
            '30d' => 'INTERVAL 30 DAY',
            '90d' => 'INTERVAL 90 DAY',
            '12m' => 'INTERVAL 12 MONTH',
            default => 'INTERVAL 7 DAY',
        };

        $stmt = $this->pdo->prepare(
            "SELECT recorded_at, value FROM readings
             WHERE reservoir_id = :rid AND metric = :metric
               AND recorded_at >= NOW() - {$intervalo}
             ORDER BY recorded_at ASC"
        );
        $stmt->execute(['rid' => $reservoirId, 'metric' => $metric]);
        $linhas = $stmt->fetchAll();

        $labels = array_map(fn($l) => $l['recorded_at'], $linhas);
        $values = array_map(fn($l) => (float) $l['value'], $linhas);

        return ['labels' => $labels, 'values' => $values];
    }

    public function readings(string $reservoirId, string $metric, int $limit = 5): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM readings
             WHERE reservoir_id = :rid AND metric = :metric
             ORDER BY recorded_at DESC
             LIMIT :lim'
        );
        $stmt->bindValue('rid', $reservoirId);
        $stmt->bindValue('metric', $metric);
        $stmt->bindValue('lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    private function jsonList(string $tabela, string $reservoirId): array
    {
        $stmt = $this->pdo->prepare("SELECT data FROM {$tabela} WHERE reservoir_id = :rid");
        $stmt->execute(['rid' => $reservoirId]);
        return array_map(
            fn($linha) => json_decode($linha['data'], true),
            $stmt->fetchAll()
        );
    }

    public function sensors(string $reservoirId): array
    {
        return $this->jsonList('sensors', $reservoirId);
    }

    public function phPoints(string $reservoirId): array
    {
        return $this->jsonList('ph_points', $reservoirId);
    }

    public function rainStations(string $reservoirId): array
    {
        return $this->jsonList('rain_stations', $reservoirId);
    }

    public function operationEvents(string $reservoirId): array
    {
        return $this->jsonList('operation_events', $reservoirId);
    }

    public function maintenances(string $reservoirId): array
    {
        return $this->jsonList('maintenances', $reservoirId);
    }

    public function alerts(string $reservoirId = 'all', string $severity = 'all', string $status = 'all'): array
    {
        $sql = 'SELECT data FROM alerts WHERE 1=1';
        $params = [];

        if ($reservoirId !== 'all') {
            $sql .= ' AND reservoir_id = :rid';
            $params['rid'] = $reservoirId;
        }
        if ($severity !== 'all') {
            $sql .= ' AND severity = :sev';
            $params['sev'] = $severity;
        }
        if ($status !== 'all') {
            $sql .= ' AND status = :status';
            $params['status'] = $status;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return array_map(fn($l) => json_decode($l['data'], true), $stmt->fetchAll());
    }

    public function reports(string $reservoirId = 'all', string $type = 'all', string $status = 'all'): array
    {
        $sql = 'SELECT data FROM reports WHERE 1=1';
        $params = [];

        if ($reservoirId !== 'all') {
            $sql .= ' AND reservoir_id = :rid';
            $params['rid'] = $reservoirId;
        }
        if ($type !== 'all') {
            $sql .= ' AND type = :type';
            $params['type'] = $type;
        }
        if ($status !== 'all') {
            $sql .= ' AND status = :status';
            $params['status'] = $status;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return array_map(fn($l) => json_decode($l['data'], true), $stmt->fetchAll());
    }

    public function scheduledReports(): array
    {
        $stmt = $this->pdo->query('SELECT data FROM scheduled_reports');
        return array_map(fn($l) => json_decode($l['data'], true), $stmt->fetchAll());
    }

    public function settings(): array
    {
        $stmt = $this->pdo->query('SELECT setting_key, data FROM settings');
        $resultado = [];
        foreach ($stmt->fetchAll() as $linha) {
            $resultado[$linha['setting_key']] = json_decode($linha['data'], true);
        }
        return $resultado;
    }
}
