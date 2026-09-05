<?php
/**
 * Aquapulse — contrato de acesso aos dados de monitoramento.
 *
 * ============================================================================
 * PONTO DE TROCA PARA O BANCO DE DADOS
 * ============================================================================
 *
 * Services e endpoints dependem APENAS desta interface — nunca dos arquivos
 * simulados. Para conectar o banco, a equipe responsável deve criar
 * `backend/src/Repositories/Pdo/PdoMonitoringRepository.php` implementando
 * exatamente estes métodos e devolvendo as mesmas estruturas, e trocar a
 * instância em `backend/src/Support/Container.php`.
 *
 * Nenhuma tela, JavaScript, endpoint ou contrato de API precisa ser alterado.
 *
 * Ver docs/database-handoff.md para as entidades e campos esperados.
 */

declare(strict_types=1);

namespace Aquapulse\Contracts;

interface MonitoringRepositoryInterface
{
    /**
     * Todas as empresas.
     *
     * @return array<int,array{id:string,code:string,name:string,manager:string,status:string,status_label:string}>
     */
    public function companies(): array;

    /**
     * Represas, opcionalmente filtradas por empresa.
     *
     * @param string $companyId ID da empresa ou 'all'.
     * @return array<int,array<string,mixed>> Cada item traz os campos descritos
     *         em docs/database-handoff.md (entidade "represas").
     */
    public function reservoirs(string $companyId = 'all'): array;

    /** Uma represa pelo ID, ou null se não existir. */
    public function reservoir(string $reservoirId): ?array;

    /**
     * Série temporal de uma métrica.
     *
     * @param string $reservoirId ID da represa.
     * @param string $metric      level|flow|inflow|outflow|ph|storage|precipitation
     * @param string $period      24h|7d|30d|90d|12m
     * @return array{labels:array<int,string>,values:array<int,float>}
     */
    public function series(string $reservoirId, string $metric, string $period): array;

    /**
     * Últimas leituras registradas de uma métrica.
     *
     * @return array<int,array<string,mixed>>
     */
    public function readings(string $reservoirId, string $metric, int $limit = 5): array;

    /** Sensores de uma represa. @return array<int,array<string,mixed>> */
    public function sensors(string $reservoirId): array;

    /** Pontos de coleta de pH. @return array<int,array<string,mixed>> */
    public function phPoints(string $reservoirId): array;

    /** Estações pluviométricas. @return array<int,array<string,mixed>> */
    public function rainStations(string $reservoirId): array;

    /**
     * Alertas filtrados.
     *
     * @param string $reservoirId ID ou 'all'.
     * @param string $severity    all|critical|attention|info
     * @param string $status      all|new|analysis|resolved
     * @return array<int,array<string,mixed>>
     */
    public function alerts(string $reservoirId = 'all', string $severity = 'all', string $status = 'all'): array;

    /**
     * Relatórios filtrados.
     *
     * @return array<int,array<string,mixed>>
     */
    public function reports(string $reservoirId = 'all', string $type = 'all', string $status = 'all'): array;

    /** Relatórios agendados. @return array<int,array<string,mixed>> */
    public function scheduledReports(): array;

    /** Eventos operacionais recentes. @return array<int,array<string,mixed>> */
    public function operationEvents(string $reservoirId): array;

    /** Próximas manutenções. @return array<int,array<string,mixed>> */
    public function maintenances(string $reservoirId): array;

    /** Configurações e limites do sistema. @return array<string,mixed> */
    public function settings(): array;
}
