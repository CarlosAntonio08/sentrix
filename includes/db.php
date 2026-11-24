<?php
require_once __DIR__ . '/../config/config.php';

function ir_para_instalador(): void
{
    if (PHP_SAPI === 'cli') return;
    header('Location: instalar.php');
    exit;
}

if (!BANCO_GERENCIADO && !is_file(ARQUIVO_INSTALADO)) {
    ir_para_instalador();
}

$dsn = 'mysql:host=' . DB_HOST
    . ';port=' . DB_PORT
    . ';dbname=' . DB_NAME
    . ';charset=' . DB_CHARSET;

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

$usaTls = getenv('RENDER') === 'true'
    || str_ends_with(strtolower(rtrim(DB_HOST, '.')), '.tidbcloud.com')
    || filter_var(getenv('SENTRIX_DB_SSL'), FILTER_VALIDATE_BOOLEAN);

try {
    if ($usaTls) {
        $ca = getenv('SENTRIX_DB_SSL_CA') ?: '/etc/ssl/certs/ca-certificates.crt';
        if (!is_readable($ca)) {
            throw new PDOException('O certificado CA do banco não está acessível.');
        }
        $options[PDO::MYSQL_ATTR_SSL_CA] = $ca;
        $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = true;
    }

    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    if ($usaTls && !$pdo->query("SHOW SESSION STATUS LIKE 'Ssl_cipher'")->fetchColumn(1)) {
        throw new PDOException('O banco não negociou TLS.');
    }
    $pdo->exec("SET time_zone = '" . date('P') . "'");
} catch (PDOException $e) {
    error_log('[Sentrix] Falha na conexão: ' . $e->getMessage());
    http_response_code(500);
    exit('Não foi possível conectar ao banco de dados.');
}
