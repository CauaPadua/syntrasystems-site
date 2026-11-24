<?php
/**
 * Aquapulse — cadastro das contas de acesso (tabelas users e user_reservoir_access).
 *
 *   create()   conta nova, cadastrada pelo administrador
 *   update()   nome, e-mail, cargo, situação, senha e represas
 *   insert()   gravação usada por create() e pela aprovação de um pedido
 *
 * Conta não é excluída: suspender basta para impedir a entrada e preserva o
 * histórico de quem tratou cada alerta.
 */

declare(strict_types=1);

namespace Aquapulse\Repositories;

use Aquapulse\Support\ApiException;
use Aquapulse\Support\Fields;
use Aquapulse\Support\Guard;
use PDO;

final class AccountRepository
{
    public const STATUS = ['active' => 'Ativa', 'suspended' => 'Suspensa'];

    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /** @param array<string,mixed> $dados name, email, role, status, password, reservoirs */
    public function create(array $dados): int
    {
        $f = new Fields($dados);
        $campos = $this->validar($f);
        $senha = $f->password('password');
        $this->emailLivre($f, $campos['email'], 0);
        $f->check();

        $this->pdo->beginTransaction();
        $id = $this->insert($campos, password_hash($senha, PASSWORD_DEFAULT));
        $this->pdo->commit();

        return $id;
    }

    /**
     * Altera uma conta. Senha em branco mantém a atual.
     *
     * O administrador não muda o próprio cargo nem se suspende: assim sempre
     * existe ao menos um administrador ativo no sistema.
     *
     * @param array<string,mixed> $dados
     * @param int                 $quem  administrador que está fazendo a alteração
     */
    public function update(int $id, array $dados, int $quem): void
    {
        $stmt = $this->pdo->prepare('SELECT role, status FROM users WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $atual = $stmt->fetch();

        if ($atual === false) {
            throw new ApiException('ACCOUNT_NOT_FOUND', 'Conta não encontrada.', 404);
        }

        $f = new Fields($dados);
        $campos = $this->validar($f);
        $trocarSenha = ($dados['password'] ?? '') !== '';
        $senha = $trocarSenha ? $f->password('password') : '';
        $this->emailLivre($f, $campos['email'], $id);

        if ($id === $quem && $campos['role'] !== $atual['role']) {
            $f->error('role', 'Você não pode mudar o próprio cargo.');
        }
        if ($id === $quem && $campos['status'] !== $atual['status']) {
            $f->error('status', 'Você não pode suspender a própria conta.');
        }
        $f->check();

        $this->pdo->beginTransaction();
        $this->pdo->prepare('UPDATE users SET name = :name, email = :email, role = :role, status = :status WHERE id = :id')
            ->execute(['name' => $campos['name'], 'email' => $campos['email'], 'role' => $campos['role'], 'status' => $campos['status'], 'id' => $id]);

        if ($trocarSenha) {
            $this->pdo->prepare('UPDATE users SET password_hash = :hash WHERE id = :id')
                ->execute(['hash' => password_hash($senha, PASSWORD_DEFAULT), 'id' => $id]);
        }
        $this->vincular($id, $campos['reservoirs']);
        $this->pdo->commit();
    }

    /**
     * Grava a conta e os vínculos. Os campos já precisam estar conferidos, e quem
     * chama abre a transação.
     *
     * @param array{name:string,email:string,role:string,status:string,reservoirs:array<int,string>} $campos
     */
    public function insert(array $campos, string $hash): int
    {
        $this->pdo->prepare(
            'INSERT INTO users (name, email, role, status, password_hash)
             VALUES (:name, :email, :role, :status, :hash)'
        )->execute(['name' => $campos['name'], 'email' => $campos['email'], 'role' => $campos['role'], 'status' => $campos['status'], 'hash' => $hash]);

        $id = (int) $this->pdo->lastInsertId();
        $this->vincular($id, $campos['reservoirs']);

        return $id;
    }

    public function emailExists(string $email, int $exceto = 0): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM users WHERE email = :email AND id <> :exceto');
        $stmt->execute(['email' => $email, 'exceto' => $exceto]);

        return (int) $stmt->fetchColumn() > 0;
    }

    /** @return array<int,string> */
    public function allReservoirIds(): array
    {
        return $this->pdo->query('SELECT id FROM reservoirs ORDER BY sort_order, code')->fetchAll(PDO::FETCH_COLUMN);
    }

    /* ----------------------------------------------------------- internos */

    /** @return array{name:string,email:string,role:string,status:string,reservoirs:array<int,string>} */
    private function validar(Fields $f): array
    {
        $campos = [
            'name'   => $f->text('name', 3, 120, 'Informe o nome (3 a 120 caracteres).'),
            'email'  => $f->email('email'),
            'role'   => $f->option('role', array_keys(Guard::ROLES), 'Selecione o cargo.'),
            'status' => $f->option('status', array_keys(self::STATUS), 'Selecione a situação da conta.'),
        ];

        // O administrador vê todas as represas pelo cargo; os demais precisam de pelo menos uma.
        $campos['reservoirs'] = $campos['role'] === 'admin'
            ? []
            : $f->options('reservoirs', $this->allReservoirIds(), 'Selecione ao menos uma represa.');

        return $campos;
    }

    private function emailLivre(Fields $f, string $email, int $exceto): void
    {
        if (!$f->hasError('email') && $this->emailExists($email, $exceto)) {
            $f->error('email', 'Já existe uma conta com este e-mail.');
        }
    }

    /** Deixa a conta vinculada exatamente às represas informadas. @param array<int,string> $represas */
    private function vincular(int $userId, array $represas): void
    {
        $this->pdo->prepare('DELETE FROM user_reservoir_access WHERE user_id = :u')->execute(['u' => $userId]);

        $stmt = $this->pdo->prepare('INSERT INTO user_reservoir_access (user_id, reservoir_id) VALUES (:u, :r)');
        foreach ($represas as $represa) {
            $stmt->execute(['u' => $userId, 'r' => $represa]);
        }
    }
}
