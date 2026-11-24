<?php
/**
 * Arquivo de inicialização: inclua no topo de TODAS as páginas.
 * Configura a sessão de forma segura, conecta ao banco e carrega
 * as funções auxiliares (CSRF, mensagens, permissões, log...).
 */
require_once __DIR__ . '/../config/config.php';

if (session_status() === PHP_SESSION_NONE) {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $https,   // em HTTPS, o cookie só trafega criptografado
        'httponly' => true,     // JavaScript não consegue ler o cookie da sessão
        'samesite' => 'Lax',    // reduz envio do cookie por sites de terceiros
    ]);
    session_name('SENTRIXSESSID');
    session_start();
}

// Cabeçalhos de segurança básicos
if (!headers_sent()) {
    header('X-Frame-Options: DENY');                  // impede o site de ser embutido em iframe (clickjacking)
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

// Atualiza automaticamente bancos instalados por versões anteriores
if (!is_file(ARQUIVO_MIGRACAO)) {
    require_once __DIR__ . '/migracoes.php';
    aplicar_migracoes($pdo);
}
