<?php

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Este script deve ser executado via CLI.\n");
    exit(1);
}

require_once __DIR__ . '/../config/database.php';

$email = $argv[1] ?? null;
$plainPassword = $argv[2] ?? null;

if (!$email || !$plainPassword) {
    fwrite(STDERR, "Uso: php scripts/update_user_password.php <email> <nova_password>\n");
    exit(1);
}

$passwordHash = password_hash($plainPassword, PASSWORD_DEFAULT);

try {
    $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE email = ?");
    $stmt->execute([$passwordHash, $email]);

    if ($stmt->rowCount() < 1) {
        fwrite(STDERR, "Nenhum utilizador encontrado com esse email.\n");
        exit(1);
    }

    fwrite(STDOUT, "Password atualizada com hash para {$email}.\n");
} catch (Throwable $e) {
    fwrite(STDERR, "Erro ao atualizar password: {$e->getMessage()}\n");
    exit(1);
}
