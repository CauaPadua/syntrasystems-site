<?php
/**
 * Aquapulse — pedidos de quem ainda não tem conta (tabela requests).
 *
 * Dois tipos, no mesmo lugar, e os dois já chegam com a senha da pessoa:
 *   access  colaborador de uma represa monitorada: aprovar cria a conta;
 *   demo    dono de uma represa: o administrador cadastra a empresa e a represa
 *           e, ao aprovar, a conta de gestor é criada com a senha escolhida.
 * Pedidos de demonstração anteriores à senha só podem ser marcados como atendidos.
 *
 * Quem usa:
 *   api/v1/public/request.php   create()       formulário público (solicitar.php)
 *   api/v1/auth/login.php       loginNotice()  avisa quem tenta entrar antes da resposta
 *   api/v1/requests.php         demais métodos, só para o administrador
 */

declare(strict_types=1);

namespace Aquapulse\Repositories;

use Aquapulse\Support\ApiException;
use Aquapulse\Support\Clock;
use Aquapulse\Support\Fields;
use Aquapulse\Support\Guard;
use PDO;

final class RequestRepository
{
    public const TYPES = ['access' => 'Acesso', 'demo' => 'Demonstração'];
    public const STATUS = ['pending' => 'Em análise', 'approved' => 'Aprovado', 'rejected' => 'Recusado'];

    /** Cargos que um pedido pode receber. Administrador só é criado em Configurações › Usuários. */
    public const ROLES = ['operator', 'manager'];

    /** O formulário é público: acima disso em uma hora, novos pedidos são recusados. */
    private const MAX_PER_HOUR = 50;

    /** Hash descartável: iguala o tempo de resposta quando não há pedido para o e-mail. */
    private const DUMMY_HASH = '$2y$10$usuarioInexistenteUsuarioInexistenteUsuarioInexistenteUsuar';

    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /* ---------------------------------------------------- formulário público */

    /**
     * Grava um pedido novo e devolve o número dele.
     *
     * Um e-mail que já tem conta não é recusado aqui: responder isso diria a
     * qualquer pessoa quais e-mails estão cadastrados. Quem vê é o administrador.
     *
     * @param array<string,mixed> $dados
     */
    public function create(string $type, array $dados): int
    {
        $f = new Fields($dados);

        $campos = [
            'name'           => $f->text('name', 3, 120, 'Informe seu nome completo.'),
            'email'          => $f->email('email'),
            'company_name'   => $f->text('company_name', 2, 120, 'Informe a empresa.'),
            'reservoir_name' => $f->text('reservoir_name', 2, 120, 'Informe a represa.'),
            'phone'          => null,
            'registration'   => null,
            'city'           => null,
            'manager_name'   => null,
            'password_hash'  => null,
        ];

        $senha = $f->password('password');                     // é com ela que a pessoa entra depois de aprovada
        if (!$f->hasError('password') && ($dados['password_confirm'] ?? '') !== $senha) {
            $f->error('password_confirm', 'As senhas não são iguais.');
        }

        if ($type === 'access') {
            $campos['registration'] = $f->text('registration', 1, 40, 'Informe sua matrícula na empresa.');
            $campos['manager_name'] = $f->text('manager_name', 3, 120, 'Informe o nome do seu gestor direto.');
            $campos['message'] = $f->text('message', 10, 500, 'Explique por que precisa do acesso (10 a 500 caracteres).');
        } else {
            $campos['phone'] = $f->pattern('phone', '/^(?=(?:\D*\d){8})[0-9 ()+\-]{8,30}$/', 'Informe um telefone com DDD.');
            $campos['city'] = $f->text('city', 2, 120, 'Informe a cidade.');
            $campos['message'] = $f->text('message', 0, 500, 'Use até 500 caracteres.');
        }
        $f->check();

        $this->conferirLimites($type, $campos['email']);
        $campos['password_hash'] = password_hash($senha, PASSWORD_DEFAULT);

        $this->pdo->prepare(
            'INSERT INTO requests (type, name, email, phone, registration, company_name, reservoir_name, city,
                                   manager_name, message, password_hash, created_at)
             VALUES (:type, :name, :email, :phone, :registration, :company_name, :reservoir_name, :city,
                     :manager_name, :message, :password_hash, :created_at)'
        )->execute($campos + ['type' => $type, 'created_at' => Clock::sql()]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Aviso para quem tenta entrar com um pedido ainda não aprovado. Só aparece
     * para quem digitou a senha do próprio pedido.
     *
     * @return array{code:string,message:string}|null
     */
    public function loginNotice(string $email, string $password): ?array
    {
        // A mesma pessoa pode ter um pedido de acesso e um de demonstração, com senhas
        // diferentes: vale o mais recente cuja senha confere.
        $stmt = $this->pdo->prepare(
            "SELECT status, password_hash, decision_note
               FROM requests
              WHERE email = :email AND password_hash IS NOT NULL
              ORDER BY id DESC
              LIMIT 3"
        );
        $stmt->execute(['email' => $email]);
        $pedidos = $stmt->fetchAll();

        $pedido = null;
        foreach ($pedidos as $p) {
            if (password_verify($password, (string) $p['password_hash'])) {
                $pedido = $p;
                break;
            }
        }
        if ($pedidos === []) {
            password_verify($password, self::DUMMY_HASH);                 // mesmo tempo de resposta de quem tem pedido
        }
        if ($pedido === null) {
            return null;
        }

        return $pedido['status'] === 'rejected'
            ? ['code' => 'REQUEST_REJECTED', 'message' => 'Seu pedido foi recusado: ' . rtrim((string) $pedido['decision_note'], '.') . '.']
            : ['code' => 'REQUEST_PENDING', 'message' => 'Seu pedido está em análise. Assim que for aprovado, você entra com esta mesma senha.'];
    }

    /* ---------------------------------------------------------- administrador */

    /** @return array<int,array<string,mixed>> */
    public function all(string $type = 'all', string $status = 'all'): array
    {
        $sql = $this->consulta() . ' WHERE 1 = 1';
        $params = [];

        if ($type !== 'all') {
            $sql .= ' AND p.type = :type';
            $params['type'] = $type;
        }
        if ($status !== 'all') {
            $sql .= ' AND p.status = :status';
            $params['status'] = $status;
        }
        // os que esperam resposta primeiro, do mais antigo para o mais novo
        $sql .= " ORDER BY p.status = 'pending' DESC,
                           CASE WHEN p.status = 'pending' THEN p.created_at END ASC,
                           p.decided_at DESC, p.id DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return array_map([$this, 'formatar'], $stmt->fetchAll());
    }

    /** Quantos pedidos esperam resposta (contador do menu). */
    public function pendingCount(): int
    {
        return (int) $this->pdo->query("SELECT COUNT(*) FROM requests WHERE status = 'pending'")->fetchColumn();
    }

    /** @return array{pending:int,access:int,demo:int,decided:int} */
    public function summary(): array
    {
        $l = $this->pdo->query(
            "SELECT SUM(status = 'pending')                      AS pending,
                    SUM(status = 'pending' AND type = 'access')  AS access,
                    SUM(status = 'pending' AND type = 'demo')    AS demo,
                    SUM(status <> 'pending')                     AS decided
               FROM requests"
        )->fetch();

        return array_map('intval', $l);
    }

    /**
     * Aprova um pedido (de acesso ou de demonstração): cria a conta com a senha que a
     * pessoa escolheu e liga às represas. Tudo em uma transação: ou a conta nasce
     * completa, ou nada muda.
     *
     * @param array<string,mixed> $dados role, reservoirs
     */
    public function approve(int $id, array $dados, int $adminId): void
    {
        $contas = new AccountRepository($this->pdo);

        $f = new Fields($dados);
        $role = $f->option('role', self::ROLES, 'Selecione o cargo.');
        $represas = $f->options('reservoirs', $contas->allReservoirIds(), 'Selecione ao menos uma represa.');
        $f->check();

        $propria = !$this->pdo->inTransaction();                  // dentro de uma transação maior (testes), usa a dela
        if ($propria) {
            $this->pdo->beginTransaction();
        }
        try {
            $pedido = $this->pendente($id);

            if ($pedido['password_hash'] === null) {
                throw new ApiException('NO_PASSWORD', 'Este pedido foi enviado antes de o formulário pedir senha. Crie a conta em Configurações › Usuários e marque o pedido como atendido.', 409);
            }
            if ($contas->emailExists($pedido['email'])) {
                throw new ApiException('ACCOUNT_EXISTS', 'Já existe uma conta com este e-mail. Ajuste a conta em Configurações › Usuários e recuse este pedido.', 409);
            }

            $contas->insert([
                'name' => $pedido['name'], 'email' => $pedido['email'], 'role' => $role,
                'status' => 'active', 'reservoirs' => $represas,
            ], $pedido['password_hash']);

            $nomes = $this->nomesDasRepresas($represas);
            $this->decidir($id, 'approved', 'Conta criada: ' . Guard::roleLabel($role) . ' em ' . implode(', ', $nomes), $adminId);
            if ($propria) {
                $this->pdo->commit();
            }
        } catch (\Throwable $e) {
            if ($propria) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Marca como atendido um pedido de demonstração que chegou sem senha (anterior a
     * ela). Com senha, o caminho é aprovar, que cria a conta.
     *
     * @param array<string,mixed> $dados note (opcional)
     */
    public function conclude(int $id, array $dados, int $adminId): void
    {
        $f = new Fields($dados);
        $nota = $f->text('note', 0, 300, 'Use até 300 caracteres.');
        $f->check();

        if ($this->pendente($id, 'demo')['password_hash'] !== null) {
            throw new ApiException('USE_APPROVE', 'Este pedido tem senha: aprove-o para criar a conta do dono da represa.', 409);
        }
        $this->decidir($id, 'approved', $nota === '' ? 'Contato feito' : $nota, $adminId);
    }

    /** Recusa um pedido. O motivo é mostrado à pessoa quando ela tenta entrar. @param array<string,mixed> $dados note */
    public function reject(int $id, array $dados, int $adminId): void
    {
        $f = new Fields($dados);
        $motivo = $f->text('note', 5, 300, 'Escreva o motivo da recusa (5 a 300 caracteres).');
        $f->check();

        $this->pendente($id);
        $this->decidir($id, 'rejected', $motivo, $adminId);
    }

    /** Apaga um pedido já respondido (a conta criada por ele continua). */
    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare("DELETE FROM requests WHERE id = :id AND status <> 'pending'");
        $stmt->execute(['id' => $id]);

        if ($stmt->rowCount() === 0) {
            throw new ApiException('REQUEST_NOT_FOUND', 'Só é possível excluir um pedido já respondido.', 409);
        }
    }

    /* --------------------------------------------------------------- internos */

    /** Uma pessoa, um pedido por vez; e um teto por hora, porque o formulário é público. */
    private function conferirLimites(string $type, string $email): void
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM requests WHERE email = :email AND type = :type AND status = 'pending'");
        $stmt->execute(['email' => $email, 'type' => $type]);

        if ((int) $stmt->fetchColumn() > 0) {
            throw new ApiException('REQUEST_PENDING', 'Já existe um pedido em análise com este e-mail. Aguarde a resposta.', 409);
        }

        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM requests WHERE created_at > :desde');
        $stmt->execute(['desde' => Clock::sql(Clock::now()->modify('-1 hour'))]);

        if ((int) $stmt->fetchColumn() >= self::MAX_PER_HOUR) {
            throw new ApiException('TOO_MANY_REQUESTS', 'Recebemos muitos pedidos agora. Tente de novo em uma hora.', 429);
        }
    }

    /**
     * O pedido, se ainda espera resposta. Dois administradores não respondem o mesmo pedido.
     *
     * @return array<string,mixed>
     */
    private function pendente(int $id, ?string $type = null): array
    {
        $stmt = $this->pdo->prepare('SELECT id, type, status, name, email, password_hash FROM requests WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $pedido = $stmt->fetch();

        if ($pedido === false || ($type !== null && $pedido['type'] !== $type)) {
            throw new ApiException('REQUEST_NOT_FOUND', 'Pedido não encontrado.', 404);
        }
        if ($pedido['status'] !== 'pending') {
            throw new ApiException('REQUEST_DECIDED', 'Este pedido já foi respondido.', 409);
        }
        return $pedido;
    }

    /** Grava a resposta. Na aprovação o hash da senha sai do pedido: ele já está na conta. */
    private function decidir(int $id, string $status, string $nota, int $adminId): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE requests
                SET status = :status, decision_note = :nota, decided_by = :admin, decided_at = :agora,
                    password_hash = IF(:status2 = 'approved', NULL, password_hash)
              WHERE id = :id AND status = 'pending'"
        );
        $stmt->execute([
            'status' => $status, 'status2' => $status, 'nota' => mb_substr($nota, 0, 300, 'UTF-8'),
            'admin' => $adminId, 'agora' => Clock::sql(), 'id' => $id,
        ]);

        if ($stmt->rowCount() === 0) {
            throw new ApiException('REQUEST_DECIDED', 'Este pedido já foi respondido.', 409);
        }
    }

    /** @param array<int,string> $ids @return array<int,string> */
    private function nomesDasRepresas(array $ids): array
    {
        $marcas = implode(', ', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare("SELECT name FROM reservoirs WHERE id IN ({$marcas}) ORDER BY sort_order, code");
        $stmt->execute($ids);

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    private function consulta(): string
    {
        // LEFT JOIN: o pedido aparece mesmo sem conta com o e-mail dele e mesmo sem resposta
        return 'SELECT p.id, p.type, p.status, p.name, p.email, p.phone, p.registration, p.company_name, p.reservoir_name,
                       p.city, p.manager_name, p.message, p.decided_at, p.decision_note, p.created_at,
                       p.password_hash IS NOT NULL AS has_password,
                       a.name AS decided_by, c.id AS account_id
                  FROM requests p
                  LEFT JOIN users a ON a.id = p.decided_by
                  LEFT JOIN users c ON c.email = p.email';
    }

    /** @param array<string,mixed> $l */
    private function formatar(array $l): array
    {
        $status = (string) $l['status'];
        $type = (string) $l['type'];

        return [
            'id'             => (int) $l['id'],
            'type'           => $type,
            'type_label'     => self::TYPES[$type] ?? $type,
            'status'         => $status,
            'status_label'   => $type === 'demo' && $status === 'approved' && $l['account_id'] === null ? 'Atendido' : (self::STATUS[$status] ?? $status),
            'name'           => (string) $l['name'],
            'email'          => (string) $l['email'],
            'phone'          => $l['phone'],
            'registration'   => $l['registration'],
            'company_name'   => (string) $l['company_name'],
            'reservoir_name' => (string) $l['reservoir_name'],
            'city'           => $l['city'],
            'manager_name'   => $l['manager_name'],
            'message'        => (string) $l['message'],
            'has_account'    => $l['account_id'] !== null,
            'has_password'   => (bool) $l['has_password'],          // pode ser aprovado (a conta nasce com esta senha)
            'created_at'     => Clock::dateTime(Clock::parse((string) $l['created_at'])),
            'decided_at'     => $l['decided_at'] === null ? null : Clock::dateTime(Clock::parse((string) $l['decided_at'])),
            'decided_by'     => $l['decided_by'],
            'decision_note'  => $l['decision_note'],
        ];
    }
}
