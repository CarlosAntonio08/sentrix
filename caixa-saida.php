<?php
/**
 * Caixa de saída de demonstração.
 *
 * Mostra os e-mails gerados para a conta que está no meio do login — e só ela:
 * a pessoa precisa ter acertado a senha nesta mesma sessão. Serve para
 * apresentar o fluxo sem internet ou antes de configurar o Gmail.
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/login_email.php';

$pendente = $_SESSION['login_pendente'] ?? null;
if (!$pendente) {
    flash('info', 'A caixa de saída fica disponível durante o login, depois de digitar a senha.');
    redirecionar('login.php');
}
if (!caixa_saida_permitida($pendente)) {
    flash('erro', 'O e-mail foi enviado de verdade: abra o link pela sua caixa de entrada.');
    redirecionar('verificar-email.php');
}

$stmt = $pdo->prepare(
    'SELECT id, destinatario, assunto, corpo_html, status, criado_em FROM emails_enviados
     WHERE usuario_id = ? AND id > ? ORDER BY id DESC LIMIT 5'
);
$stmt->execute([$pendente['id'], (int) $pendente['email_id_min']]);
$emails = $stmt->fetchAll();

$titulo = 'Caixa de saída (demonstração) — Sentrix Guard Flow';
require_once __DIR__ . '/includes/header.php';
?>

<main>
  <section class="page-hero page-hero-compact">
    <div class="page-hero-orb"></div>
    <div class="page-hero-inner">
      <span class="section-label">// MODO DEMONSTRAÇÃO</span>
      <h1 class="page-hero-title">CAIXA DE <span class="accent">SAÍDA</span></h1>
      <p class="page-hero-desc">É assim que o e-mail chega para a pessoa. Clique no botão do e-mail para concluir o login.</p>
    </div>
  </section>

  <section class="page-section">
    <div class="page-section-inner caixa-saida">
      <?php if (!$emails): ?>
        <p class="panel-empty">Nenhum e-mail gerado ainda.</p>
      <?php endif; ?>

      <?php foreach ($emails as $i => $m): ?>
      <article class="email-preview <?= $i > 0 ? 'email-antigo' : '' ?>">
        <header class="email-cabecalho">
          <div><span class="email-rotulo">De</span> <?= e(MAIL_REMETENTE_NOME) ?></div>
          <div><span class="email-rotulo">Para</span> <?= e($m['destinatario']) ?></div>
          <div><span class="email-rotulo">Assunto</span> <strong><?= e($m['assunto']) ?></strong></div>
          <div class="email-data"><?= date('d/m/Y H:i:s', strtotime($m['criado_em'])) ?>
            <?= $i > 0 ? ' · <span class="tag-plan">link substituído pelo mais recente</span>' : '' ?></div>
        </header>
        <?php if ($i === 0): ?>
          <iframe class="email-frame" title="Conteúdo do e-mail"
                  sandbox="allow-top-navigation-by-user-activation allow-popups"
                  srcdoc="<?= e(str_replace('<head>', '<head><base target="_top">', $m['corpo_html'])) ?>"></iframe>
        <?php endif; ?>
      </article>
      <?php endforeach; ?>

      <p class="form-hint"><a href="verificar-email.php" class="panel-link">← Voltar</a></p>
    </div>
  </section>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
