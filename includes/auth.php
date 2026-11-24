<?php
/**
 * Guarda de autenticação — inclua no topo de qualquer página que exija login.
 * Também recarrega os dados do usuário do banco a cada acesso, para que
 * mudanças de cargo ou desativação tenham efeito imediato.
 */
require_once __DIR__ . '/bootstrap.php';

$usuarioLogado = null;

if (!empty($_SESSION['usuario_id'])) {
    // Sessão expirada por inatividade?
    if (isset($_SESSION['ultimo_acesso']) && time() - $_SESSION['ultimo_acesso'] > SESSAO_TIMEOUT) {
        $_SESSION = [];
        session_regenerate_id(true);
        flash('info', 'Sua sessão expirou por inatividade. Entre novamente.');
        redirecionar('login.php');
    }
    $_SESSION['ultimo_acesso'] = time();

    $stmt = $pdo->prepare('SELECT id, nome, email, cargo FROM usuarios WHERE id = ? AND ativo = 1');
    $stmt->execute([$_SESSION['usuario_id']]);
    $usuarioLogado = $stmt->fetch() ?: null;
}

if (!$usuarioLogado) {
    // Limpa só os dados de acesso (mantém um login por e-mail que esteja em andamento)
    unset($_SESSION['usuario_id'], $_SESSION['usuario_nome'], $_SESSION['usuario_cargo'], $_SESSION['ultimo_acesso']);
    if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        exit(json_encode(['ok' => false, 'erro' => 'Não autenticado.']));
    }
    flash('info', 'Faça login para continuar.');
    redirecionar('login.php');
}

$usuarioLogado['id'] = (int) $usuarioLogado['id'];
$_SESSION['usuario_nome']  = $usuarioLogado['nome'];
$_SESSION['usuario_cargo'] = $usuarioLogado['cargo'];
