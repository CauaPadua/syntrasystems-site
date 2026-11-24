<?php
/**
 * Aquapulse — cadastro de empresas e represas (tabelas companies e reservoirs).
 *
 *   createCompany()   / updateCompany()
 *   createReservoir() / updateReservoir()
 *
 * Só o administrador chega aqui. O id usado nas URLs e o código são montados a
 * partir do nome e não mudam depois ("Represa Santa Clara" -> santa-clara, RSC-001).
 *
 * Empresa e represa não são excluídas: as leituras, os alertas e os relatórios
 * dependem delas. Uma empresa que saiu do sistema fica com a situação "Inativa".
 */

declare(strict_types=1);

namespace Aquapulse\Repositories;

use Aquapulse\Support\ApiException;
use Aquapulse\Support\Fields;
use PDO;

final class RegistryRepository
{
    public const COMPANY_STATUS = ['active' => 'Ativa', 'inactive' => 'Inativa'];

    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /* ------------------------------------------------------------- empresas */

    /** @param array<string,mixed> $dados name, manager, status */
    public function createCompany(array $dados): string
    {
        $campos = $this->validarEmpresa($dados, null);
        $campos['id'] = $this->novoId('companies', $campos['name']);
        $campos['code'] = $this->novoCodigo('companies', $campos['name']);

        $this->pdo->prepare(
            'INSERT INTO companies (id, code, name, manager, status, status_label, sort_order)
             VALUES (:id, :code, :name, :manager, :status, :status_label, :sort_order)'
        )->execute($campos + ['sort_order' => $this->proximaOrdem('companies')]);

        return $campos['id'];
    }

    /** @param array<string,mixed> $dados */
    public function updateCompany(string $id, array $dados): void
    {
        $this->existe('companies', $id, 'COMPANY_NOT_FOUND', 'Empresa não encontrada.');
        $campos = $this->validarEmpresa($dados, $id);

        $this->pdo->prepare(
            'UPDATE companies SET name = :name, manager = :manager, status = :status, status_label = :status_label WHERE id = :id'
        )->execute($campos + ['id' => $id]);
    }

    /** @param array<string,mixed> $dados @return array{name:string,manager:string,status:string,status_label:string} */
    private function validarEmpresa(array $dados, ?string $id): array
    {
        $f = new Fields($dados);
        $campos = [
            'name'    => $f->text('name', 2, 120, 'Informe o nome da empresa.'),
            'manager' => $f->text('manager', 3, 120, 'Informe o responsável pela empresa.'),
            'status'  => $f->option('status', array_keys(self::COMPANY_STATUS), 'Selecione a situação.'),
        ];
        $this->nomeLivre($f, 'companies', $campos['name'], $id, 'Já existe uma empresa com este nome.');
        $f->check();

        return $campos + ['status_label' => self::COMPANY_STATUS[$campos['status']]];
    }

    /* ------------------------------------------------------------- represas */

    /** @param array<string,mixed> $dados ver validarRepresa() */
    public function createReservoir(array $dados): string
    {
        $campos = $this->validarRepresa($dados, null);
        $campos['id'] = $this->novoId('reservoirs', preg_replace('/^represa\s+/iu', '', $campos['name']) ?: $campos['name']);
        $campos['code'] = $this->novoCodigo('reservoirs', $campos['name']);
        $campos['sort_order'] = $this->proximaOrdem('reservoirs');

        $this->pdo->prepare(
            'INSERT INTO reservoirs (id, company_id, code, name, city, basin, lat, lng, coordinates_label,
                                     capacity_hm3, useful_volume_hm3, technical_reserve_hm3, daily_consumption_hm3,
                                     cota_min_m, cota_max_m, cota_alert_m, cota_spill_m, sort_order)
             VALUES (:id, :company_id, :code, :name, :city, :basin, :lat, :lng, :coordinates_label,
                     :capacity_hm3, :useful_volume_hm3, :technical_reserve_hm3, :daily_consumption_hm3,
                     :cota_min_m, :cota_max_m, :cota_alert_m, :cota_spill_m, :sort_order)'
        )->execute($campos);

        return $campos['id'];
    }

    /** @param array<string,mixed> $dados */
    public function updateReservoir(string $id, array $dados): void
    {
        $this->existe('reservoirs', $id, 'RESERVOIR_NOT_FOUND', 'Represa não encontrada.');

        $this->pdo->prepare(
            'UPDATE reservoirs
                SET company_id = :company_id, name = :name, city = :city, basin = :basin, lat = :lat, lng = :lng,
                    coordinates_label = :coordinates_label, capacity_hm3 = :capacity_hm3,
                    useful_volume_hm3 = :useful_volume_hm3, technical_reserve_hm3 = :technical_reserve_hm3,
                    daily_consumption_hm3 = :daily_consumption_hm3, cota_min_m = :cota_min_m, cota_max_m = :cota_max_m,
                    cota_alert_m = :cota_alert_m, cota_spill_m = :cota_spill_m
              WHERE id = :id'
        )->execute($this->validarRepresa($dados, $id) + ['id' => $id]);
    }

    /** @param array<string,mixed> $dados @return array<string,mixed> */
    private function validarRepresa(array $dados, ?string $id): array
    {
        $f = new Fields($dados);
        $empresas = $this->pdo->query('SELECT id FROM companies')->fetchAll(PDO::FETCH_COLUMN);

        $campos = [
            'company_id' => $f->option('company_id', $empresas, 'Selecione a empresa.'),
            'name'       => $f->text('name', 3, 120, 'Informe o nome da represa.'),
            'city'       => $f->text('city', 2, 120, 'Informe a cidade.'),
            'basin'      => $f->text('basin', 2, 120, 'Informe a bacia hidrográfica.'),
            'lat'        => $f->number('lat', -90, 90, 'Use de -90 a 90 (ex.: -22,3875).'),
            'lng'        => $f->number('lng', -180, 180, 'Use de -180 a 180 (ex.: -47,6922).'),

            'capacity_hm3'          => $f->number('capacity_hm3', 0.1, 1000000, 'Informe a capacidade, maior que zero.'),
            'useful_volume_hm3'     => $f->number('useful_volume_hm3', 0, 1000000, 'Informe o volume útil.'),
            'technical_reserve_hm3' => $f->number('technical_reserve_hm3', 0, 1000000, 'Informe a reserva técnica.'),
            'daily_consumption_hm3' => $f->number('daily_consumption_hm3', 0, 100000, 'Informe o consumo médio por dia.'),

            'cota_min_m'   => $f->number('cota_min_m', -400, 9000, 'Informe a cota mínima.'),
            'cota_max_m'   => $f->number('cota_max_m', -400, 9000, 'Informe a cota máxima.'),
            'cota_alert_m' => $f->number('cota_alert_m', -400, 9000, 'Informe a cota de alerta.'),
            'cota_spill_m' => $f->number('cota_spill_m', -400, 9000, 'Informe a cota de vertimento.'),
        ];

        $this->nomeLivre($f, 'reservoirs', $campos['name'], $id, 'Já existe uma represa com este nome.');

        // Regras entre campos: o volume cabe na capacidade e as cotas sobem nesta ordem.
        if (!$f->hasError('capacity_hm3', 'useful_volume_hm3') && $campos['useful_volume_hm3'] > $campos['capacity_hm3']) {
            $f->error('useful_volume_hm3', 'Não pode ser maior que a capacidade.');
        }
        if (!$f->hasError('capacity_hm3', 'technical_reserve_hm3') && $campos['technical_reserve_hm3'] > $campos['capacity_hm3']) {
            $f->error('technical_reserve_hm3', 'Não pode ser maior que a capacidade.');
        }
        if (!$f->hasError('cota_min_m', 'cota_max_m', 'cota_alert_m', 'cota_spill_m')) {
            if ($campos['cota_max_m'] <= $campos['cota_min_m']) {
                $f->error('cota_max_m', 'Precisa ser maior que a cota mínima.');
            }
            if ($campos['cota_alert_m'] <= $campos['cota_min_m']) {
                $f->error('cota_alert_m', 'Precisa ser maior que a cota mínima.');
            }
            if ($campos['cota_spill_m'] < $campos['cota_alert_m']) {
                $f->error('cota_spill_m', 'Precisa ser igual ou maior que a cota de alerta.');
            }
            if ($campos['cota_spill_m'] > $campos['cota_max_m']) {
                $f->error('cota_spill_m', 'Não pode passar da cota máxima.');
            }
        }
        $f->check();

        return $campos + ['coordinates_label' => self::coordenadas($campos['lat'], $campos['lng'])];
    }

    /** -22.3875, -47.6922 -> 22°23'15" S, 47°41'32" W */
    public static function coordenadas(float $lat, float $lng): string
    {
        $gms = static function (float $valor): string {
            $segundos = (int) round(abs($valor) * 3600);
            return sprintf('%d°%02d\'%02d"', intdiv($segundos, 3600), intdiv($segundos, 60) % 60, $segundos % 60);
        };

        return $gms($lat) . ($lat < 0 ? ' S' : ' N') . ', ' . $gms($lng) . ($lng < 0 ? ' W' : ' E');
    }

    /* ------------------------------------------------------------- internos */

    /** Nomes de tabela vêm só deste arquivo, nunca da requisição. */
    private function existe(string $tabela, string $id, string $codigo, string $mensagem): void
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM {$tabela} WHERE id = :id");
        $stmt->execute(['id' => $id]);

        if ((int) $stmt->fetchColumn() === 0) {
            throw new ApiException($codigo, $mensagem, 404);
        }
    }

    private function nomeLivre(Fields $f, string $tabela, string $nome, ?string $exceto, string $mensagem): void
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM {$tabela} WHERE name = :nome AND id <> :exceto");
        $stmt->execute(['nome' => $nome, 'exceto' => $exceto ?? '']);

        if ((int) $stmt->fetchColumn() > 0) {
            $f->error('name', $mensagem);
        }
    }

    /** Identificador a partir do nome: "Águas do Norte" -> aguas-do-norte (com -2, -3... se já existir). */
    private function novoId(string $tabela, string $nome): string
    {
        $base = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower(self::semAcento($nome))), '-');
        $base = trim(substr($base === '' ? 'cadastro' : $base, 0, 34), '-');

        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM {$tabela} WHERE id = :id");
        for ($n = 1, $id = $base; ; $n++, $id = $base . '-' . $n) {
            $stmt->execute(['id' => $id]);
            if ((int) $stmt->fetchColumn() === 0 && $id !== 'all') {     // "all" é o filtro "todas" nas URLs
                return $id;
            }
        }
    }

    /** Código a partir das iniciais e de um número em sequência: "Represa Santa Clara" -> RSC-004. */
    private function novoCodigo(string $tabela, string $nome): string
    {
        preg_match_all('/[A-Z0-9]+/', strtoupper(self::semAcento($nome)), $m);
        $palavras = array_values(array_filter($m[0], static fn (string $p): bool => strlen($p) > 2)) ?: $m[0];

        $iniciais = count($palavras) === 1
            ? substr($palavras[0], 0, 3)
            : substr(implode('', array_map(static fn (string $p): string => $p[0], $palavras)), 0, 4);

        $ultimo = (int) $this->pdo->query("SELECT MAX(CAST(SUBSTRING_INDEX(code, '-', -1) AS UNSIGNED)) FROM {$tabela}")->fetchColumn();

        return sprintf('%s-%03d', $iniciais === '' ? 'AQP' : $iniciais, $ultimo + 1);
    }

    private function proximaOrdem(string $tabela): int
    {
        return min(255, 1 + (int) $this->pdo->query("SELECT MAX(sort_order) FROM {$tabela}")->fetchColumn());
    }

    private static function semAcento(string $texto): string
    {
        return (string) preg_replace('/&([a-z])(?:acute|grave|circ|tilde|uml|cedil|ring);/i', '$1', htmlentities($texto, ENT_NOQUOTES, 'UTF-8'));
    }
}
