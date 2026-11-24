<?php
require_once __DIR__ . '/bootstrap.php';

$paginaAtual = basename($_SERVER['PHP_SELF']);
$logado      = !empty($_SESSION['usuario_id']);
$nomeUsuario = $_SESSION['usuario_nome'] ?? '';
$cargoNav    = $_SESSION['usuario_cargo'] ?? '';
$primeiroNome = explode(' ', trim($nomeUsuario))[0] ?: 'usuário';

// Links do menu: públicos x área logada (filtrados por cargo)
if ($logado) {
    $menu = [
        ['painel.php',        'Painel'],
        ['monitoramento.php', 'Monitoramento'],
        ['alertas-ia.php',    'Alertas'],
        ['gps.php',           'GPS'],
        ['dispositivos.php',  'Dispositivos'],
    ];
    if ($cargoNav === 'admin') {
        $menu[] = ['usuarios.php', 'Admin'];
    }
} else {
    $menu = [
        ['index.php#inicio',          'Início'],
        ['index.php#sobre',           'Sobre'],
        ['index.php#funcionalidades', 'Funcionalidades'],
        ['download.php',              'Aplicativo'],
    ];
}
$paginasAdmin = ['usuarios.php', 'logs.php', 'configuracoes-email.php'];
$ativo = function (string $href) use ($paginaAtual, $paginasAdmin): string {
    $arquivo = strtok($href, '#');
    if ($arquivo === 'usuarios.php' && in_array($paginaAtual, $paginasAdmin, true)) return 'active';
    if ($arquivo === 'gps.php' && $paginaAtual === 'cercas.php') return 'active';
    return ($arquivo === $paginaAtual && !str_contains($href, '#')) ? 'active' : '';
};
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="description" content="Sentrix Guard Flow — plataforma de monitoramento e segurança inteligente.">
    <title><?= e($titulo ?? SITE_NAME) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Rajdhani:wght@400;500;600;700&family=Share+Tech+Mono&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <!-- App instalável (PWA) -->
    <link rel="manifest" href="manifest.webmanifest">
    <meta name="theme-color" content="#0a0a0c">
    <link rel="icon" type="image/png" href="assets/icons/favicon-32.png">
    <link rel="apple-touch-icon" href="assets/icons/apple-touch-icon.png">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Sentrix">
    <?php if (!empty($usarMapa)): ?>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
          integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
    <?php endif; ?>
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
</head>
<body>

<a class="skip-link" href="#conteudo">Pular para o conteúdo</a>

<header class="navbar" id="navbar">
    <div class="navbar-inner">
        <a href="<?= $logado ? 'painel.php' : 'index.php' ?>" class="logo-block" aria-label="Sentrix Guard Flow — início">
            <span class="logo-name">SENTRIX</span>
            <span class="logo-sub">GUARD FLOW</span>
        </a>

        <nav class="nav-links" aria-label="Menu principal">
            <?php foreach ($menu as [$href, $rotulo]): ?>
                <a href="<?= e($href) ?>" class="nav-link <?= $ativo($href) ?>"><?= e($rotulo) ?></a>
            <?php endforeach; ?>
        </nav>

        <?php if ($logado): ?>
            <div class="nav-user">
                <span class="nav-user-name" title="<?= e(rotulo('cargo', $cargoNav)) ?>">Olá, <?= e($primeiroNome) ?></span>
                <form method="POST" action="logout.php" class="inline-form">
                    <?= csrf_campo() ?>
                    <button type="submit" class="btn-outline btn-sm">Sair</button>
                </form>
            </div>
        <?php else: ?>
            <a href="login.php" class="btn-primary nav-entrar">Entrar</a>
        <?php endif; ?>

        <button class="menu-toggle" id="menuToggle" aria-label="Abrir menu" aria-expanded="false" aria-controls="mobileMenu">
            <span></span><span></span><span></span>
        </button>
    </div>

    <div class="mobile-menu" id="mobileMenu">
        <?php foreach ($menu as [$href, $rotulo]): ?>
            <a href="<?= e($href) ?>" class="nav-link <?= $ativo($href) ?>"><?= e($rotulo) ?></a>
        <?php endforeach; ?>
        <?php if ($logado): ?>
            <form method="POST" action="logout.php">
                <?= csrf_campo() ?>
                <button type="submit" class="btn-primary btn-block">Sair (<?= e($primeiroNome) ?>)</button>
            </form>
        <?php else: ?>
            <a href="login.php" class="btn-primary btn-block">Entrar</a>
        <?php endif; ?>
    </div>
</header>

<div id="conteudo"></div>
