<?php
/**
 * Destino do link enviado por e-mail.
 *
 * GET  → confere o link e mostra o botão "Entrar". O link NÃO é consumido aqui,
 *        porque alguns serviços de e-mail (Gmail, Outlook) "abrem" os links
 *        automaticamente para checar vírus — isso não pode gastar o acesso.
 * POST → consome o link (uso único) e cria a sessão neste navegador/app.
 */
require_once 'includes/bootstrap.php';
require_once 'includes/login_email.php';

header('Referrer-Policy: no-referrer'); // o token não vaza para sites externos
header('Cache-Control: no-store');

$token = (string) ($_POST['token'] ?? $_GET['token'] ?? '');
$dados = buscar_token_valido($pdo, $token);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $dados) {
    csrf_verificar();
    if (consumir_token($pdo, (int) $dados['token_id'])) {
        finalizar_login($pdo, $dados, 'link');
        flash('ok', 'Acesso confirmado. Bem-vindo(a) ao Sentrix!');
        redirecionar('painel.php');
    }
    $dados = null; // usado por outra requisição no mesmo instante
}

if (!$dados && $token !== '' && preg_match('/^[a-f0-9]{64}$/', $token)) {
    registrar_log($pdo, null, 'login_link_invalido', 'Tentativa com link de acesso inválido, usado ou expirado');
}

$titulo = 'Entrar — Sentrix Guard Flow';
require_once 'includes/header.php';
?>

<main>
  <section class="page-hero auth-hero-section">
    <div class="page-hero-orb"></div>
    <div class="auth-grid auth-grid-single">
      <div class="auth-panel">
        <div class="auth-ring" aria-hidden="true">
          <div class="auth-ring-core">
            <svg viewBox="0 0 24 24" width="30" height="30" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
              <?php if ($dados): ?><path d="M20 6 9 17l-5-5"></path><?php else: ?><path d="M18 6 6 18M6 6l12 12"></path><?php endif; ?>
            </svg>
          </div>
        </div>
        <div class="auth-card text-center">
          <?php if ($dados): ?>
            <h1 class="block-title">Confirmar acesso</h1>
            <p class="instalador-texto">Entrar no Sentrix como<br><strong class="td-destaque"><?= e($dados['nome']) ?></strong>
              <span class="td-mono">(<?= e(mascarar_email($dados['email'])) ?>)</span></p>
            <form method="POST" action="confirmar-login.php">
              <?= csrf_campo() ?>
              <input type="hidden" name="token" value="<?= e($token) ?>">
              <button type="submit" class="btn-primary btn-block">Entrar no Sentrix</button>
            </form>
            <p class="form-hint">Não pediu este acesso? Feche esta página — nada será feito.</p>
          <?php else: ?>
            <h1 class="block-title">Link inválido ou expirado</h1>
            <p class="instalador-texto">Este link já foi usado, foi substituído por um mais novo ou passou de
              <?= (int) LOGIN_LINK_MINUTOS ?> minutos. Faça o login de novo para receber outro.</p>
            <a href="login.php" class="btn-primary btn-block">Ir para o login</a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </section>
</main>

<?php require_once 'includes/footer.php'; ?>
