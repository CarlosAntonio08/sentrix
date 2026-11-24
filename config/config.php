<?php
/**
 * Configurações gerais do Sentrix Guard Flow.
 * Ajuste aqui as credenciais do banco conforme o seu ambiente
 * (XAMPP, Laragon, WAMP, MAMP ou servidor de hospedagem).
 *
 * Cada valor também pode ser definido por variável de ambiente
 * (ex.: SENTRIX_DB_PASS), útil em hospedagem/produção.
 */

define('SITE_NAME', 'Sentrix Guard Flow');

// true  = mostra detalhes técnicos de erros na tela (use só no seu computador)
// false = mostra mensagem genérica e grava o detalhe no log do PHP (produção)
define('APP_DEBUG', filter_var(getenv('SENTRIX_DEBUG') ?: 'false', FILTER_VALIDATE_BOOLEAN));

// No Render e com banco externo configurado, use o banco existente.
define('BANCO_GERENCIADO', getenv('RENDER') === 'true' || (string) getenv('SENTRIX_DB_HOST') !== '');

// Banco de dados (MySQL / MariaDB)
// O instalador (instalar.php) grava em config/config.local.php as credenciais
// digitadas por você quando forem diferentes do padrão do XAMPP (root, sem senha).
if (!BANCO_GERENCIADO && is_file(__DIR__ . '/config.local.php')) {
    require __DIR__ . '/config.local.php';
}
defined('DB_HOST')    || define('DB_HOST', getenv('SENTRIX_DB_HOST') ?: '127.0.0.1');
defined('DB_PORT')    || define('DB_PORT', getenv('SENTRIX_DB_PORT') ?: '3306');
defined('DB_NAME')    || define('DB_NAME', getenv('SENTRIX_DB_NAME') ?: 'sentrix_guard_flow');
defined('DB_USER')    || define('DB_USER', getenv('SENTRIX_DB_USER') ?: 'root');
defined('DB_PASS')    || define('DB_PASS', getenv('SENTRIX_DB_PASS') !== false ? getenv('SENTRIX_DB_PASS') : '');
define('DB_CHARSET', 'utf8mb4');

// Arquivo criado pelo instalador quando o banco está pronto
define('ARQUIVO_INSTALADO', __DIR__ . '/instalado.lock');

// E-mail (login com link de confirmação)
// Os valores reais são gravados pela tela Admin > E-mail em config/email.local.php.
// Sem usuário/senha configurados, o sistema funciona em "modo demonstração":
// os e-mails não saem de verdade e aparecem numa caixa de saída na tela.
if (is_file(__DIR__ . '/email.local.php')) {
    require __DIR__ . '/email.local.php';
}
defined('MAIL_SMTP_HOST')        || define('MAIL_SMTP_HOST', 'smtp.gmail.com');
defined('MAIL_SMTP_PORTA')       || define('MAIL_SMTP_PORTA', 587);
defined('MAIL_USUARIO')          || define('MAIL_USUARIO', '');      // ex.: seuemail@gmail.com
defined('MAIL_SENHA')            || define('MAIL_SENHA', '');        // senha de app do Google (16 letras)
defined('MAIL_REMETENTE_NOME')   || define('MAIL_REMETENTE_NOME', 'Sentrix Guard Flow');
defined('LOGIN_CONFIRMAR_EMAIL') || define('LOGIN_CONFIRMAR_EMAIL', true); // exige o link do e-mail para entrar
defined('APP_URL')               || define('APP_URL', getenv('RENDER_EXTERNAL_URL') ?: '');           // endereço do site usado nos links (vazio = automático)
define('LOGIN_LINK_MINUTOS', 15);                                     // validade do link de acesso
define('ARQUIVO_MIGRACAO', __DIR__ . '/banco-v2.lock');

// Sessão: tempo máximo sem atividade antes de pedir login de novo (segundos)
define('SESSAO_TIMEOUT', 30 * 60);

// Login: quantas tentativas erradas por IP são permitidas na janela abaixo
define('LOGIN_MAX_TENTATIVAS', 5);
define('LOGIN_JANELA_MINUTOS', 15);

date_default_timezone_set('America/Sao_Paulo');
