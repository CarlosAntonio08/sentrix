<?php
/**
 * INSTALADOR AUTOMÁTICO
 *
 * Aberto automaticamente na primeira vez que o sistema roda: cria o banco de
 * dados a partir do schema.sql com um clique (sem phpMyAdmin).
 *
 * Depois de instalado, só um administrador logado pode usar esta página, para
 * restaurar os dados de demonstração (útil antes da apresentação).
 */
require_once __DIR__ . '/includes/instalador.php';

$jaInstalado = is_file(ARQUIVO_INSTALADO);

if ($jaInstalado) {
    // Modo restauração: exige admin logado
    require_once __DIR__ . '/includes/auth.php';
    exigir_permissao('gerenciar_usuarios');
} else {
    require_once __DIR__ . '/includes/helpers.php';
    if (session_status() === PHP_SESSION_NONE) {
        session_name('SENTRIXSESSID');
        session_start();
    }
}

$problemas = verificar_requisitos();
$erro      = '';
$sucesso   = false;
$cred = [
    'host'    => DB_HOST,
    'porta'   => (string) DB_PORT,
    'usuario' => DB_USER,
    'senha'   => DB_PASS,
];

// Primeira visita: se o banco já existe (importado pelo phpMyAdmin), só marca como instalado
if (!$jaInstalado && !$problemas && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    try {
        $srv = conectar_servidor($cred['host'], $cred['porta'], $cred['usuario'], $cred['senha']);
        if (banco_instalado($srv)) {
            marcar_instalado();
            header('Location: index.php');
            exit;
        }
    } catch (PDOException $e) {
        $erro = explicar_erro_mysql($e);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$problemas) {
    csrf_verificar();
    if (!$jaInstalado) {
        $cred = [
            'host'    => trim($_POST['host'] ?? '127.0.0.1') ?: '127.0.0.1',
            'porta'   => trim($_POST['porta'] ?? '3306') ?: '3306',
            'usuario' => trim($_POST['usuario'] ?? 'root') ?: 'root',
            'senha'   => (string) ($_POST['senha'] ?? ''),
        ];
    }
    try {
        $srv = conectar_servidor($cred['host'], $cred['porta'], $cred['usuario'], $cred['senha']);
        executar_schema($srv);
        if (!$jaInstalado) {
            gravar_config_local($cred['host'], $cred['porta'], $cred['usuario'], $cred['senha']);
        }
        marcar_instalado();
        $sucesso = true;

        if ($jaInstalado) {
            // O banco foi recriado: encerra a sessão para entrar de novo
            $_SESSION = [];
            session_regenerate_id(true);
            flash('ok', 'Dados de demonstração restaurados. Entre novamente.');
            header('Location: login.php');
            exit;
        }
    } catch (PDOException $e) {
        $erro = explicar_erro_mysql($e);
    }
}

$e = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalação — Sentrix Guard Flow</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<main class="instalador">
    <div class="instalador-card">
        <div class="logo-block instalador-logo">
            <span class="logo-name">SENTRIX</span>
            <span class="logo-sub">GUARD FLOW</span>
        </div>

        <?php if ($sucesso): ?>
            <h1 class="block-title">Tudo pronto! ✓</h1>
            <p class="msg msg-ok">O banco de dados foi criado com os dados de demonstração.</p>
            <p class="instalador-texto">Use um destes usuários para entrar:</p>
            <div class="panel-table-wrap">
                <table class="panel-table">
                    <thead><tr><th>E-mail</th><th>Senha</th><th>Cargo</th></tr></thead>
                    <tbody>
                        <tr><td class="td-mono">admin@sentrixguard.com</td><td class="td-mono">admin123</td><td>Administrador</td></tr>
                        <tr><td class="td-mono">operador@sentrixguard.com</td><td class="td-mono">operador123</td><td>Operador</td></tr>
                        <tr><td class="td-mono">visitante@sentrixguard.com</td><td class="td-mono">visitante123</td><td>Visualizador</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="form-actions">
                <a href="index.php" class="btn-outline btn-sm">Ver o site</a>
                <a href="login.php" class="btn-primary">Entrar no sistema</a>
            </div>

        <?php elseif ($jaInstalado): ?>
            <h1 class="block-title">Restaurar dados de demonstração</h1>
            <p class="instalador-texto">Apaga <strong>tudo</strong> o que foi cadastrado (dispositivos, alertas, usuários
                novos, logs…) e volta aos dados iniciais. Útil antes da apresentação.</p>
            <?php if ($erro): ?><p class="msg msg-erro">⚠ <?= $e($erro) ?></p><?php endif; ?>
            <form method="POST" action="instalar.php" data-confirmar="Apagar todos os dados e restaurar a demonstração?">
                <?= csrf_campo() ?>
                <div class="form-actions">
                    <a href="usuarios.php" class="btn-outline btn-sm">Cancelar</a>
                    <button type="submit" class="btn-primary">Restaurar dados</button>
                </div>
            </form>

        <?php else: ?>
            <h1 class="block-title">Instalação</h1>
            <p class="instalador-texto">Falta só criar o banco de dados. Com o XAMPP, basta clicar em <strong>Instalar</strong>
                — os valores abaixo já são os padrões dele.</p>

            <?php foreach ($problemas as $p): ?><p class="msg msg-erro">⚠ <?= $e($p) ?></p><?php endforeach; ?>
            <?php if ($erro): ?><p class="msg msg-erro">⚠ <?= $e($erro) ?></p><?php endif; ?>

            <?php if (!$problemas): ?>
            <form method="POST" action="instalar.php" class="form-stack">
                <?= csrf_campo() ?>
                <details class="instalador-avancado" <?= $erro ? 'open' : '' ?>>
                    <summary>Configurações do MySQL (só mude se o seu não for o padrão)</summary>
                    <div class="form-row">
                        <div>
                            <label class="form-label" for="host">Servidor</label>
                            <input class="form-input" id="host" name="host" value="<?= $e($cred['host']) ?>">
                        </div>
                        <div>
                            <label class="form-label" for="porta">Porta</label>
                            <input class="form-input" id="porta" name="porta" value="<?= $e($cred['porta']) ?>">
                        </div>
                    </div>
                    <div class="form-row">
                        <div>
                            <label class="form-label" for="usuario">Usuário</label>
                            <input class="form-input" id="usuario" name="usuario" value="<?= $e($cred['usuario']) ?>">
                        </div>
                        <div>
                            <label class="form-label" for="senha">Senha</label>
                            <input class="form-input" id="senha" name="senha" type="password" value="<?= $e($cred['senha']) ?>" placeholder="(em branco no XAMPP)">
                        </div>
                    </div>
                </details>
                <button type="submit" class="btn-primary form-submit">Instalar</button>
            </form>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</main>
<script src="assets/js/main.js"></script>
</body>
</html>
