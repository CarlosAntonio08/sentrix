<?php
require_once 'includes/bootstrap.php';

// Logout só por POST com token CSRF (um link externo não consegue deslogar o usuário)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirecionar(!empty($_SESSION['usuario_id']) ? 'painel.php' : 'login.php');
}
csrf_verificar();

if (!empty($_SESSION['usuario_id'])) {
    registrar_log($pdo, (int) $_SESSION['usuario_id'], 'logout', 'Logout realizado');
}

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
session_destroy();

session_start();
session_regenerate_id(true);
flash('ok', 'Você saiu do sistema.');
redirecionar('login.php');
