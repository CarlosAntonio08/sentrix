<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/login_email.php';

// Já está logado? Vai direto para o painel.
if (!empty($_SESSION['usuario_id'])) {
    redirecionar('painel.php');
}

$erro  = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verificar();
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';

    // Proteção contra força bruta: limita as senhas erradas para o mesmo
    // e-mail vindas do mesmo IP (e um teto geral por IP, contra varredura)
    $descricaoFalha = 'Falha de login para ' . mb_substr($email, 0, 160);
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) AS total, COALESCE(SUM(descricao = ?), 0) AS deste_email
         FROM logs_eventos
         WHERE tipo_evento = 'login_falha' AND ip_origem = ?
           AND criado_em > (NOW() - INTERVAL " . (int) LOGIN_JANELA_MINUTOS . " MINUTE)"
    );
    $stmt->execute([$descricaoFalha, ip_cliente()]);
    $falhas = $stmt->fetch();

    if ((int) $falhas['deste_email'] >= LOGIN_MAX_TENTATIVAS || (int) $falhas['total'] >= LOGIN_MAX_TENTATIVAS * 4) {
        $erro = 'Muitas tentativas sem sucesso. Aguarde ' . LOGIN_JANELA_MINUTOS . ' minutos e tente novamente.';
    } elseif ($email === '' || $senha === '') {
        $erro = 'Preencha e-mail e senha.';
    } else {
        $stmt = $pdo->prepare('SELECT id, nome, email, cargo, senha_hash, ativo FROM usuarios WHERE email = ?');
        $stmt->execute([$email]);
        $usuario = $stmt->fetch();

        if ($usuario && $usuario['ativo'] && password_verify($senha, $usuario['senha_hash'])) {
            // Atualiza o hash se o algoritmo padrão do PHP tiver mudado
            if (password_needs_rehash($usuario['senha_hash'], PASSWORD_DEFAULT)) {
                $pdo->prepare('UPDATE usuarios SET senha_hash = ? WHERE id = ?')
                    ->execute([password_hash($senha, PASSWORD_DEFAULT), $usuario['id']]);
            }

            if (!LOGIN_CONFIRMAR_EMAIL) {
                finalizar_login($pdo, $usuario);
                redirecionar('painel.php');
            }

            // Etapa 2: envia o link de acesso por e-mail
            session_regenerate_id(true);
            $_SESSION['login_pendente'] = [
                'id'           => (int) $usuario['id'],
                'email'        => $usuario['email'],
                // a caixa de saída de demonstração só mostra e-mails criados a partir daqui
                'email_id_min' => (int) $pdo->query('SELECT COALESCE(MAX(id), 0) FROM emails_enviados')->fetchColumn(),
                'envios'       => 1,
                'ultimo_envio' => time(),
            ];
            $r = enviar_link_login($pdo, $usuario);
            $_SESSION['login_pendente']['status'] = $r['status'];
            $_SESSION['login_pendente']['erro']   = $r['erro'];
            redirecionar('verificar-email.php');
        }

        // Mensagem genérica: não revela se o e-mail existe
        $erro = 'E-mail ou senha incorretos.';
        registrar_log($pdo, $usuario ? (int) $usuario['id'] : null, 'login_falha', $descricaoFalha);
    }
}

$titulo = 'Login — Sentrix Guard Flow';
require_once __DIR__ . '/includes/header.php';
?>

<main>
  <section class="page-hero auth-hero-section">
    <div class="page-hero-orb"></div>

    <div class="auth-grid">
      <div class="auth-copy">
        <span class="section-label reveal">// ÁREA RESTRITA</span>
        <h1 class="page-hero-title reveal reveal-delay-1">
          ACESSE SUA
          <span class="accent">CONTA</span>
        </h1>
        <p class="page-hero-desc reveal reveal-delay-2">
          Entre com seu e-mail e senha. Para sua segurança, enviaremos um
          link de confirmação para o seu e-mail — é por ele que você acessa o painel.
        </p>
      </div>

      <div class="auth-panel reveal reveal-delay-3">
        <div class="auth-ring" aria-hidden="true">
          <div class="auth-ring-core">
            <svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <rect x="4" y="11" width="16" height="9" rx="2"></rect>
              <path d="M8 11V7a4 4 0 0 1 8 0v4"></path>
            </svg>
          </div>
        </div>

        <div class="auth-card">
          <?= flash_exibir() ?>
          <?php if ($erro): ?>
            <p class="msg msg-erro" role="alert">⚠ <?= e($erro) ?></p>
          <?php endif; ?>

          <form method="POST" action="login.php" class="form-stack">
            <?= csrf_campo() ?>
            <label class="form-label" for="email">E-mail</label>
            <input type="email" id="email" name="email" class="form-input" placeholder="seu@email.com"
                   value="<?= e($email) ?>" autocomplete="username" required autofocus>

            <label class="form-label" for="senha">Senha</label>
            <input type="password" id="senha" name="senha" class="form-input" placeholder="Sua senha"
                   autocomplete="current-password" required>

            <button type="submit" class="btn-primary form-submit">Entrar</button>
          </form>

          <p class="auth-switch">
            Não tem uma conta? <a href="cadastro.php">Cadastre-se</a>
          </p>
        </div>
      </div>
    </div>
  </section>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
