<?php
/**
 * Aquapulse — gerador de comando SQL para o primeiro usuário.
 *
 * Uso local, uma única vez: rode este arquivo pelo terminal com
 *   php gerar_usuario.php
 * e cole o INSERT gerado no seu banco. Depois, apague ou não suba
 * este arquivo com uma senha real dentro dele.
 *
 * O script NÃO grava nada: só pergunta os dados no terminal e imprime o
 * comando SQL pronto, já com a senha convertida em hash. A tabela de destino
 * é criada por create_users_table.sql.
 */

declare(strict_types=1);

echo "Nome completo: ";
$nome = trim(fgets(STDIN));                                                  // fgets(STDIN) lê uma linha digitada no terminal; trim remove a quebra de linha

echo "E-mail: ";
$email = trim(fgets(STDIN));

echo "Senha (sera transformada em hash, nao fica salva em texto puro): ";
$senha = trim(fgets(STDIN));                                                 // atenção: trim remove espaços nas pontas da senha digitada

echo "Funcao (ex: Administrador, Operador): ";
$funcao = trim(fgets(STDIN));                                                // vai para a coluna role

$hash = password_hash($senha, PASSWORD_DEFAULT);                             // gera o hash (bcrypt, com sal aleatório); é o mesmo formato conferido por password_verify() no login
$emailNormalizado = mb_strtolower($email, 'UTF-8');                          // e-mail em minúsculas, igual à normalização feita no login

$nomeEscapado = addslashes($nome);                                           // escapa aspas para o texto não quebrar o SQL gerado
$emailEscapado = addslashes($emailNormalizado);
$funcaoEscapada = addslashes($funcao);

echo "\n--- Copie o comando abaixo e rode no seu banco ---\n\n";
echo "INSERT INTO users (name, email, role, password_hash) VALUES ('{$nomeEscapado}', '{$emailEscapado}', '{$funcaoEscapada}', '{$hash}');\n"; // o id é gerado pelo AUTO_INCREMENT da tabela
