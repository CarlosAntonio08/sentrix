<?php
/**
 * Conexão PDO com o banco de dados.
 * As credenciais vêm de config/config.php — ajuste lá, não aqui.
 *
 * Se o sistema ainda não foi instalado (ou o banco não existe / está
 * inacessível), o navegador é levado para o instalador automático.
 */
require_once __DIR__ . '/../config/config.php';

function ir_para_instalador(): void
{
    if (PHP_SAPI === 'cli') return;
    header('Location: instalar.php');
    exit;
}

if (!is_file(ARQUIVO_INSTALADO)) {
    ir_para_instalador();
}

$dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    // Usa no MySQL o mesmo fuso horário do PHP (datas de NOW() batem com as da tela)
    $pdo->exec("SET time_zone = '" . date('P') . "'");
} catch (PDOException $e) {
    error_log('[Sentrix] Falha na conexão com o banco: ' . $e->getMessage());

    // 1049 = banco não existe · 1045 = usuário/senha recusados → instalador resolve
    if (in_array((int) $e->getCode(), [1049, 1045, 1698], true)) {
        @unlink(ARQUIVO_INSTALADO);
        ir_para_instalador();
    }

    http_response_code(500);
    $detalhe = APP_DEBUG ? '<br><small>Detalhe: ' . htmlspecialchars($e->getMessage()) . '</small>' : '';
    exit('<p style="font-family:sans-serif;padding:24px">Não foi possível conectar ao banco de dados. '
        . 'Verifique se o <strong>MySQL está rodando</strong> (no XAMPP, botão "Start" ao lado de MySQL) '
        . 'e recarregue a página.' . $detalhe . '</p>');
}
