<?php
/**
 * Aquapulse — gerador de comando SQL para o primeiro usuário.
 *
 * Uso local, uma única vez: rode este arquivo pelo terminal com
 *   php gerar_usuario.php
 * e cole o INSERT gerado no seu banco. Depois, apague ou não suba
 * este arquivo com uma senha real dentro dele.
 */

declare(strict_types=1);

echo "Nome completo: ";
$nome = trim(fgets(STDIN));

echo "E-mail: ";
$email = trim(fgets(STDIN));

echo "Senha (sera transformada em hash, nao fica salva em texto puro): ";
$senha = trim(fgets(STDIN));

echo "Funcao (ex: Administrador, Operador): ";
$funcao = trim(fgets(STDIN));

$hash = password_hash($senha, PASSWORD_DEFAULT);
$emailNormalizado = mb_strtolower($email, 'UTF-8');

$nomeEscapado = addslashes($nome);
$emailEscapado = addslashes($emailNormalizado);
$funcaoEscapada = addslashes($funcao);

echo "\n--- Copie o comando abaixo e rode no seu banco ---\n\n";
echo "INSERT INTO users (name, email, role, password_hash) VALUES ('{$nomeEscapado}', '{$emailEscapado}', '{$funcaoEscapada}', '{$hash}');\n";
