<?php
require_once __DIR__ . '/../config/config.php';

function ir_para_instalador(): void
{
    if (PHP_SAPI === 'cli') return;
    header('Location: instalar.php');
    exit;
}

if (!getenv('SENTRIX_DB_HOST') && !is_file(ARQUIVO_INSTALADO)) {
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

if (getenv('SENTRIX_DB_SSL') === 'true') {
    $options[PDO::MYSQL_ATTR_SSL_CA] =
        '/etc/ssl/certs/ca-certificates.crt';

    $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = true;
}

try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    $pdo->exec("SET time_zone = '" . date('P') . "'");
} catch (PDOException $e) {
    error_log('[Sentrix] Falha na conexão: ' . $e->getMessage());
    http_response_code(500);
    exit('Não foi possível conectar ao banco de dados.');
}
