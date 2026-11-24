<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (!empty($_SESSION['usuario_id'])) {
    redirecionar('painel.php');
}

$erro = '';
$nome = $email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verificar();
    $nome      = trim($_POST['nome'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $senha     = $_POST['senha'] ?? '';
    $confirmar = $_POST['confirmar_senha'] ?? '';
    $aceite    = !empty($_POST['aceite']);

    if ($nome === '' || $email === '' || $senha === '' || $confirmar === '') {
        $erro = 'Preencha todos os campos.';
    } elseif (mb_strlen($nome) > 120) {
        $erro = 'O nome pode ter no máximo 120 caracteres.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 160) {
        $erro = 'Informe um e-mail válido.';
    } elseif (!senha_valida($senha)) {
        $erro = 'A senha deve ter no mínimo 8 caracteres, com letras e números.';
    } elseif ($senha !== $confirmar) {
        $erro = 'As senhas não coincidem.';
    } elseif (!$aceite) {
        $erro = 'É necessário aceitar a Política de Privacidade.';
    } else {
        $stmt = $pdo->prepare('SELECT id FROM usuarios WHERE email = ?');
        $stmt->execute([$email]);

        if ($stmt->fetch()) {
            $erro = 'Este e-mail já está cadastrado.';
        } else {
            // Toda conta nova entra como "visualizador" (somente leitura).
            // Um administrador pode promovê-la em Admin > Usuários.
            $stmt = $pdo->prepare('INSERT INTO usuarios (nome, email, senha_hash, cargo) VALUES (?, ?, ?, ?)');
            $stmt->execute([$nome, $email, password_hash($senha, PASSWORD_DEFAULT), 'visualizador']);
            registrar_log($pdo, (int) $pdo->lastInsertId(), 'usuario_cadastro', 'Nova conta criada (visualizador)');

            flash('ok', 'Conta criada! Faça login. Seu acesso inicial é de visualização; um administrador pode liberar mais permissões.');
            redirecionar('login.php');
        }
    }
}

$titulo = 'Criar Conta — Sentrix Guard Flow';
require_once __DIR__ . '/includes/header.php';
?>

<main>
  <section class="page-hero auth-hero-section">
    <div class="page-hero-orb"></div>

    <div class="auth-grid">
      <div class="auth-copy">
        <span class="section-label reveal">// NOVO ACESSO</span>
        <h1 class="page-hero-title reveal reveal-delay-1">
          CRIE SUA
          <span class="accent">CONTA</span>
        </h1>
        <p class="page-hero-desc reveal reveal-delay-2">
          Cadastre-se para acompanhar câmeras, sensores, GPS e alertas.
          Novas contas começam com acesso de visualização; um administrador
          pode liberar permissões de operação.
        </p>
      </div>

      <div class="auth-panel reveal reveal-delay-3">
        <div class="auth-ring" aria-hidden="true">
          <div class="auth-ring-core">
            <svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="12" cy="8" r="4"></circle>
              <path d="M4 21v-1a7 7 0 0 1 14 0v1"></path>
            </svg>
          </div>
        </div>

        <div class="auth-card">
          <?php if ($erro): ?>
            <p class="msg msg-erro" role="alert">⚠ <?= e($erro) ?></p>
          <?php endif; ?>

          <form method="POST" action="cadastro.php" class="form-stack">
            <?= csrf_campo() ?>
            <label class="form-label" for="nome">Nome completo</label>
            <input type="text" id="nome" name="nome" class="form-input" placeholder="Seu nome" maxlength="120"
                   value="<?= e($nome) ?>" autocomplete="name" required>

            <label class="form-label" for="email">E-mail</label>
            <input type="email" id="email" name="email" class="form-input" placeholder="seu@email.com" maxlength="160"
                   value="<?= e($email) ?>" autocomplete="email" required>

            <label class="form-label" for="senha">Senha</label>
            <input type="password" id="senha" name="senha" class="form-input" placeholder="Mínimo 8 caracteres, letras e números"
                   minlength="8" autocomplete="new-password" required>

            <label class="form-label" for="confirmar_senha">Confirmar senha</label>
            <input type="password" id="confirmar_senha" name="confirmar_senha" class="form-input" placeholder="Repita a senha"
                   minlength="8" autocomplete="new-password" required>

            <label class="form-check">
              <input type="checkbox" name="aceite" value="1" required>
              <span>Li e aceito a <a href="privacidade.php" target="_blank">Política de Privacidade</a>.</span>
            </label>

            <button type="submit" class="btn-primary form-submit">Criar Conta</button>
          </form>

          <p class="auth-switch">
            Já tem uma conta? <a href="login.php">Fazer login</a>
          </p>
        </div>
      </div>
    </div>
  </section>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
