<?php
/**
 * Atualiza bancos criados por versões anteriores do projeto (sem precisar
 * reinstalar). Roda uma única vez e depois cria config/banco-v2.lock.
 */
function aplicar_migracoes(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS login_tokens (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        usuario_id INT UNSIGNED NOT NULL,
        token_hash CHAR(64) NOT NULL UNIQUE,
        expira_em DATETIME NOT NULL,
        usado_em DATETIME NULL,
        ip_origem VARCHAR(45) NULL,
        criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_tokens_usuario (usuario_id, criado_em),
        CONSTRAINT fk_token_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS emails_enviados (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        usuario_id INT UNSIGNED NULL,
        destinatario VARCHAR(160) NOT NULL,
        assunto VARCHAR(200) NOT NULL,
        corpo_html MEDIUMTEXT NOT NULL,
        status ENUM('enviado','falhou','demonstracao') NOT NULL,
        erro VARCHAR(255) NULL,
        criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_emails_usuario (usuario_id, criado_em),
        CONSTRAINT fk_email_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    @file_put_contents(ARQUIVO_MIGRACAO, 'Migração v2 aplicada em ' . date('c') . PHP_EOL);
}
