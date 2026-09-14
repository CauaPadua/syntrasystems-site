<?php
/*
 * Aquapulse — repositório de monitoramento com banco de dados MySQL (PDO).
 *
 * Implementa MonitoringRepositoryInterface lendo as tabelas criadas por
 * backend/storage/mock/sql/create_monitoring_tables.sql. É escolhido pelo
 * Support\Container quando existe a variável de ambiente DB_HOST.
 *
 * Dois estilos de tabela aparecem aqui:
 *  - tabelas com colunas normais (companies, reservoirs, readings);
 *  - tabelas "documento", em que cada linha guarda o item inteiro em JSON na
 *    coluna `data` (sensors, alerts, reports...). Assim o formato devolvido é o
 *    mesmo do arquivo simulado, sem precisar mapear coluna por coluna.
 *
 * Toda consulta que recebe valor vindo da requisição usa prepare() + parâmetros
 * nomeados (:rid, :metric...), o que evita SQL Injection.
 */
declare(strict_types=1);

namespace Aquapulse\Repositories;

use Aquapulse\Contracts\MonitoringRepositoryInterface;
use PDO;

final class PdoMonitoringRepository implements MonitoringRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)                        // recebe a conexão compartilhada de Support\Database
    {
    }

    /** Lista todas as empresas cadastradas. */
    public function companies(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM companies');                      // sem parâmetros vindos do usuário: query() direto é seguro aqui
        return $stmt->fetchAll();                                                  // todas as linhas como arrays associativos
    }

    /** Lista as represas; com um ID de empresa, só as daquela empresa. */
    public function reservoirs(string $companyId = 'all'): array
    {
        if ($companyId === 'all') {                                                // sem filtro: todas as represas
            $stmt = $this->pdo->query('SELECT * FROM reservoirs');
            return $stmt->fetchAll();
        }
        $stmt = $this->pdo->prepare('SELECT * FROM reservoirs WHERE company_id = :cid'); // filtra pela chave estrangeira company_id
        $stmt->execute(['cid' => $companyId]);                                     // o ID vai como parâmetro, nunca concatenado no SQL
        return $stmt->fetchAll();
    }

    /** Busca uma represa pelo ID; null quando não existe. */
    public function reservoir(string $reservoirId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM reservoirs WHERE id = :id');
        $stmt->execute(['id' => $reservoirId]);
        $row = $stmt->fetch();                                                     // uma linha ou false
        return $row === false ? null : $row;                                       // a interface pede null, não false
    }

    /**
     * Série temporal de uma métrica para os gráficos.
     *
     * Busca as leituras da represa e métrica pedidas dentro da janela de tempo
     * (últimas 24 h, 7/30/90 dias ou 12 meses), em ordem cronológica crescente,
     * e separa em dois arrays paralelos: datas (labels) e valores (values).
     */
    public function series(string $reservoirId, string $metric, string $period): array
    {
        $intervalo = match ($period) {                                             // traduz o período da API para a sintaxe INTERVAL do MySQL
            '24h' => 'INTERVAL 24 HOUR',
            '30d' => 'INTERVAL 30 DAY',
            '90d' => 'INTERVAL 90 DAY',
            '12m' => 'INTERVAL 12 MONTH',
            default => 'INTERVAL 7 DAY',                                           // '7d' e qualquer outro valor caem aqui
        };

        // $intervalo é inserido no texto SQL, mas é seguro: só pode ser uma das
        // cinco strings fixas do match acima, nunca um valor digitado pelo usuário.
        // NOW() é o relógio do servidor MySQL (não o Clock demonstrativo da aplicação).
        $stmt = $this->pdo->prepare(
            "SELECT recorded_at, value FROM readings
             WHERE reservoir_id = :rid AND metric = :metric
               AND recorded_at >= NOW() - {$intervalo}
             ORDER BY recorded_at ASC"
        );
        $stmt->execute(['rid' => $reservoirId, 'metric' => $metric]);
        $linhas = $stmt->fetchAll();

        $labels = array_map(fn($l) => $l['recorded_at'], $linhas);                 // eixo X: data/hora de cada leitura
        $values = array_map(fn($l) => (float) $l['value'], $linhas);               // eixo Y: valor numérico (o MySQL devolve DECIMAL como string)

        return ['labels' => $labels, 'values' => $values];
    }

    /** Últimas N leituras de uma métrica, da mais recente para a mais antiga. */
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
        $stmt->bindValue('lim', $limit, PDO::PARAM_INT);                           // LIMIT precisa receber inteiro; como string ('5') o MySQL rejeitaria
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Lê uma tabela "documento" filtrada por represa e decodifica o JSON de cada linha.
     *
     * @param string $tabela nome da tabela; só é chamado internamente com nomes fixos
     *                       (sensors, ph_points...), por isso pode ir direto no SQL
     */
    private function jsonList(string $tabela, string $reservoirId): array
    {
        $stmt = $this->pdo->prepare("SELECT data FROM {$tabela} WHERE reservoir_id = :rid");
        $stmt->execute(['rid' => $reservoirId]);
        return array_map(
            fn($linha) => json_decode($linha['data'], true),                       // transforma o texto JSON da coluna em array PHP
            $stmt->fetchAll()
        );
    }

    /** Sensores instalados na represa (tela de vazão). */
    public function sensors(string $reservoirId): array
    {
        return $this->jsonList('sensors', $reservoirId);
    }

    /** Pontos de coleta de pH (tela de pH). */
    public function phPoints(string $reservoirId): array
    {
        return $this->jsonList('ph_points', $reservoirId);
    }

    /** Estações pluviométricas (tela de precipitação). */
    public function rainStations(string $reservoirId): array
    {
        return $this->jsonList('rain_stations', $reservoirId);
    }

    /** Eventos operacionais recentes (tela de situação operacional). */
    public function operationEvents(string $reservoirId): array
    {
        return $this->jsonList('operation_events', $reservoirId);
    }

    /** Manutenções programadas (tela de situação operacional). */
    public function maintenances(string $reservoirId): array
    {
        return $this->jsonList('maintenances', $reservoirId);
    }

    /**
     * Alertas com filtros opcionais.
     *
     * O SQL é montado aos poucos: começa com "WHERE 1=1" (sempre verdadeiro) para
     * que cada filtro ativo possa ser acrescentado com "AND ..." sem se preocupar
     * se é o primeiro. Cada filtro adiciona também o seu parâmetro em $params.
     */
    public function alerts(string $reservoirId = 'all', string $severity = 'all', string $status = 'all'): array
    {
        $sql = 'SELECT data FROM alerts WHERE 1=1';
        $params = [];                                                              // valores que serão ligados aos marcadores :rid, :sev, :status

        if ($reservoirId !== 'all') {                                              // filtro por represa
            $sql .= ' AND reservoir_id = :rid';
            $params['rid'] = $reservoirId;
        }
        if ($severity !== 'all') {                                                 // filtro por gravidade (critical, attention, info)
            $sql .= ' AND severity = :sev';
            $params['sev'] = $severity;
        }
        if ($status !== 'all') {                                                   // filtro por andamento (new, analysis, resolved)
            $sql .= ' AND status = :status';
            $params['status'] = $status;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return array_map(fn($l) => json_decode($l['data'], true), $stmt->fetchAll()); // cada alerta completo está no JSON da coluna data
    }

    /** Relatórios com filtros opcionais (mesma técnica de montagem de alerts()). */
    public function reports(string $reservoirId = 'all', string $type = 'all', string $status = 'all'): array
    {
        $sql = 'SELECT data FROM reports WHERE 1=1';
        $params = [];

        if ($reservoirId !== 'all') {
            $sql .= ' AND reservoir_id = :rid';
            $params['rid'] = $reservoirId;
        }
        if ($type !== 'all') {                                                     // operational, hydrological, quality, planning
            $sql .= ' AND type = :type';
            $params['type'] = $type;
        }
        if ($status !== 'all') {                                                   // done, processing, scheduled
            $sql .= ' AND status = :status';
            $params['status'] = $status;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return array_map(fn($l) => json_decode($l['data'], true), $stmt->fetchAll());
    }

    /** Relatórios com geração automática programada. */
    public function scheduledReports(): array
    {
        $stmt = $this->pdo->query('SELECT data FROM scheduled_reports');
        return array_map(fn($l) => json_decode($l['data'], true), $stmt->fetchAll());
    }

    /**
     * Configurações do sistema, organizadas por chave.
     * Cada linha da tabela settings vira uma entrada: ['chave' => dados decodificados].
     */
    public function settings(): array
    {
        $stmt = $this->pdo->query('SELECT setting_key, data FROM settings');
        $resultado = [];
        foreach ($stmt->fetchAll() as $linha) {                                    // percorre cada configuração salva
            $resultado[$linha['setting_key']] = json_decode($linha['data'], true);
        }
        return $resultado;
    }
}
