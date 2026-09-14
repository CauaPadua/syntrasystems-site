<?php
/**
 * Aquapulse — proteção de sessão para páginas e endpoints do sistema.
 *
 * Reutiliza a autenticação e a sessão JÁ EXISTENTES (etapa do login).
 * Não existe um segundo sistema de autenticação.
 *
 *  - Páginas do dashboard sem sessão  -> redirecionam para login.php
 *  - Endpoints da API sem sessão      -> 401 em JSON (nunca HTML)
 *
 * A diferença de resposta existe porque quem chama é diferente: uma página é
 * aberta pelo navegador (faz sentido redirecionar), enquanto a API é chamada
 * pelo JavaScript via fetch (que precisa de um status HTTP para reagir —
 * ver assets/js/api-client.js, que trata o 401 levando o usuário ao login).
 */

declare(strict_types=1);

namespace Aquapulse\Support;

use Aquapulse\Auth\AuthService;

final class Guard
{
    /**
     * Exige sessão válida em um endpoint da API.
     * Sem sessão, responde 401 JSON e encerra.
     *
     * Chamado por aq_api_boot() em api/v1/_boot.php, ou seja, por todo endpoint de dados.
     *
     * @return array{id:int,name:string,email:string,role:string}
     */
    public static function requireApiSession(): array
    {
        aq_start_session();                                   // abre a sessão e aplica as regras de expiração (backend/bootstrap.php)

        $auth = new AuthService(Container::users());          // o serviço de autenticação recebe o repositório de usuários ativo (mock ou banco)
        $user = $auth->currentUser();                         // lê o user_id gravado na sessão no login e busca o usuário correspondente

        if ($user === null) {                                 // sem login, sessão expirada ou usuário removido
            ApiResponse::unauthorized();                      // responde 401 em JSON e encerra o script (exit dentro do método)
        }

        return $user;                                         // dados públicos do usuário (sem hash de senha) para o endpoint usar se precisar
    }

    /**
     * Exige sessão válida em uma página do dashboard.
     * Sem sessão, redireciona para o login e encerra.
     *
     * Chamado por dashboard/includes/page.php, incluído no topo de toda página do sistema.
     *
     * @param string $loginUrl caminho relativo até login.php (muda conforme a profundidade da página)
     * @return array{id:int,name:string,email:string,role:string}
     */
    public static function requirePageSession(string $loginUrl = '../login.php'): array
    {
        aq_start_session();

        $auth = new AuthService(Container::users());
        $user = $auth->currentUser();

        if ($user === null) {                                 // visitante não autenticado tentando abrir uma página interna
            if (!headers_sent()) {                            // o redirecionamento é um cabeçalho HTTP: só funciona se nada foi impresso antes
                header('Location: ' . $loginUrl . '?redirect=dashboard', true, 302); // 302 = redirecionamento temporário para a tela de login
            }
            // Corpo de reserva: aparece só se o redirecionamento não puder ser
            // enviado. O link é escapado com htmlspecialchars para não permitir
            // injeção de HTML pela URL.
            echo '<!DOCTYPE html><meta charset="utf-8">'
               . '<p>Sessão expirada. <a href="' . htmlspecialchars($loginUrl, ENT_QUOTES) . '">Entrar novamente</a>.</p>';
            exit;                                             // interrompe: o conteúdo da página protegida nunca é gerado
        }

        return $user;                                         // page.php guarda em $AQ_USER para mostrar nome, iniciais e e-mail na topbar
    }

    /** Iniciais do usuário para o avatar da topbar (ex.: "Ana Silva" -> "AS"). */
    public static function initials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];      // separa o nome por qualquer quantidade de espaços; "?: []" cobre falha do preg_split
        $parts = array_values(array_filter($parts));          // remove pedaços vazios e reindexa a partir de 0

        if ($parts === []) {                                  // nome vazio: mostra "?" no avatar
            return '?';
        }
        if (count($parts) === 1) {                            // nome de uma palavra só ("Ana"): usa as duas primeiras letras ("AN")
            return mb_strtoupper(mb_substr($parts[0], 0, 2, 'UTF-8'), 'UTF-8'); // funções mb_* respeitam acentos (ex.: "Élida" -> "ÉL")
        }

        return mb_strtoupper(                                 // duas ou mais palavras: primeira letra do primeiro nome + primeira do último sobrenome
            mb_substr($parts[0], 0, 1, 'UTF-8') . mb_substr($parts[count($parts) - 1], 0, 1, 'UTF-8'),
            'UTF-8'
        );
    }
}
