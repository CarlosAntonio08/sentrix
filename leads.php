<?php
/**
 * Endpoint AJAX chamado por download.php para registrar e-mails
 * de pessoas interessadas no app (tabela `leads`).
 * Proteções: CSRF, campo-armadilha anti-robô, consentimento (LGPD)
 * e limite de envios por sessão.
 */
require_once 'includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

function responder(int $codigo, array $dados): void
{
    http_response_code($codigo);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder(405, ['ok' => false, 'erro' => 'Método não permitido.']);
}
csrf_verificar();

// Robôs costumam preencher todos os campos — inclusive o invisível
if (!empty($_POST['site'])) {
    responder(200, ['ok' => true, 'mensagem' => 'E-mail registrado! Entraremos em contato.']);
}

// No máximo 5 envios a cada 10 minutos por sessão
$agora = time();
$_SESSION['leads_envios'] = array_filter($_SESSION['leads_envios'] ?? [], fn ($t) => $t > $agora - 600);
if (count($_SESSION['leads_envios']) >= 5) {
    responder(429, ['ok' => false, 'erro' => 'Muitas tentativas. Aguarde alguns minutos.']);
}
$_SESSION['leads_envios'][] = $agora;

$email = trim($_POST['email'] ?? '');
if ($email === '' || mb_strlen($email) > 160 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    responder(422, ['ok' => false, 'erro' => 'Informe um e-mail válido.']);
}
if (empty($_POST['consentimento'])) {
    responder(422, ['ok' => false, 'erro' => 'É preciso concordar com a Política de Privacidade.']);
}

try {
    $stmt = $pdo->prepare('SELECT id FROM leads WHERE email = ?');
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        responder(200, ['ok' => true, 'mensagem' => 'Você já está na nossa lista!']);
    }

    $pdo->prepare('INSERT INTO leads (email, origem, consentimento_em, ip_origem) VALUES (?, ?, NOW(), ?)')
        ->execute([$email, 'download.php', ip_cliente()]);

    responder(200, ['ok' => true, 'mensagem' => 'E-mail registrado! Avisaremos quando o app for lançado.']);
} catch (Throwable $e) {
    error_log('[Sentrix] Erro ao registrar lead: ' . $e->getMessage());
    responder(500, ['ok' => false, 'erro' => 'Não foi possível registrar agora. Tente novamente.']);
}
