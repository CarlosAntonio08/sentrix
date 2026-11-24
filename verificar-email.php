<?php
/**
 * Etapa 2 do login: "Enviamos um link para o seu e-mail".
 * Permite reenviar o link e, no modo demonstração (ou se o envio falhar),
 * abrir a caixa de saída para ver o e-mail na própria tela.
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/login_email.php';

// Consulta feita pela própria página (JavaScript) para saber se o link já foi
// aberto neste mesmo navegador — aí ela segue sozinha para o painel.
if (isset($_GET['checar'])) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    exit(json_encode(['logado' => !empty($_SESSION['usuario_id'])]));
}

if (!empty($_SESSION['usuario_id'])) {
    redirecionar('painel.php');
}
$pendente = $_SESSION['login_pendente'] ?? null;
if (!$pendente) {
    redirecionar('login.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verificar();
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'cancelar') {
        unset($_SESSION['login_pendente']);
        redirecionar('login.php');
    }

    if ($acao === 'reenviar') {
        $espera = 60 - (time() - $pendente['ultimo_envio']);
        if ($pendente['envios'] >= 5) {
            flash('erro', 'Limite de reenvios atingido. Volte e faça o login novamente.');
        } elseif ($espera > 0) {
            flash('erro', "Aguarde {$espera} segundos para pedir um novo link.");
        } else {
            $stmt = $pdo->prepare('SELECT id, nome, email, cargo FROM usuarios WHERE id = ? AND ativo = 1');
            $stmt->execute([$pendente['id']]);
            if ($usuario = $stmt->fetch()) {
                $r = enviar_link_login($pdo, $usuario);
                $_SESSION['login_pendente']['envios']++;
                $_SESSION['login_pendente']['ultimo_envio'] = time();
                $_SESSION['login_pendente']['status'] = $r['status'];
                $_SESSION['login_pendente']['erro']   = $r['erro'];
                flash('ok', 'Enviamos um novo link. O anterior deixou de valer.');
            }
        }
        redirecionar('verificar-email.php');
    }
}

$status = $pendente['status'] ?? 'enviado';
$titulo = 'Confirme pelo e-mail — Sentrix Guard Flow';
$scriptsPagina = ['verificar-email.js'];
require_once __DIR__ . '/includes/header.php';
?>

<main>
  <section class="page-hero auth-hero-section">
    <div class="page-hero-orb"></div>
    <div class="auth-grid">
      <div class="auth-copy">
        <span class="section-label">// ETAPA 2 DE 2</span>
        <h1 class="page-hero-title">CONFIRME PELO <span class="accent">E-MAIL</span></h1>
        <p class="page-hero-desc">
          Sua senha está correta. Para concluir o acesso, abra o link que enviamos —
          no computador ou no celular onde você quer usar o Sentrix.
        </p>
      </div>

      <div class="auth-panel">
        <div class="auth-ring" aria-hidden="true">
          <div class="auth-ring-core">
            <svg viewBox="0 0 24 24" width="30" height="30" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <rect x="3" y="5" width="18" height="14" rx="2"></rect>
              <path d="m3 7 9 6 9-6"></path>
            </svg>
          </div>
        </div>

        <div class="auth-card">
          <?= flash_exibir() ?>

          <?php if ($status === 'enviado'): ?>
            <p class="verif-destaque">Enviamos um link de acesso para</p>
            <p class="verif-email"><?= e(mascarar_email($pendente['email'])) ?></p>
            <ul class="verif-dicas">
              <li>O link vale por <strong><?= (int) LOGIN_LINK_MINUTOS ?> minutos</strong> e só pode ser usado uma vez.</li>
              <li>Não chegou? Confira a pasta <strong>Spam</strong> ou <strong>Promoções</strong>.</li>
              <li>Se abrir o link neste navegador, esta página segue sozinha para o painel.</li>
            </ul>
          <?php elseif ($status === 'demonstracao'): ?>
            <p class="msg msg-info">ℹ <strong>Modo demonstração:</strong> o envio de e-mails ainda não foi configurado
              (Admin → E-mail). O e-mail foi gerado e pode ser visto na caixa de saída abaixo.</p>
            <a href="caixa-saida.php" class="btn-primary btn-block">Abrir a caixa de saída</a>
          <?php else: ?>
            <p class="msg msg-erro">⚠ Não conseguimos enviar o e-mail. <?= e(explicar_erro_email($pendente['erro'] ?? '')) ?></p>
            <?php if (caixa_saida_permitida($pendente)): ?>
              <p class="form-hint">Como você está no computador que roda o sistema, dá para abrir o e-mail gerado na caixa de saída:</p>
              <a href="caixa-saida.php" class="btn-outline btn-block">Abrir a caixa de saída</a>
            <?php else: ?>
              <p class="form-hint">Tente "Reenviar link" em instantes ou avise o administrador.</p>
            <?php endif; ?>
          <?php endif; ?>

          <div class="verif-acoes">
            <form method="POST" action="verificar-email.php">
              <?= csrf_campo() ?>
              <input type="hidden" name="acao" value="reenviar">
              <button type="submit" class="btn-outline btn-sm">Reenviar link</button>
            </form>
            <form method="POST" action="verificar-email.php">
              <?= csrf_campo() ?>
              <input type="hidden" name="acao" value="cancelar">
              <button type="submit" class="btn-link">Usar outra conta</button>
            </form>
          </div>
        </div>
      </div>
    </div>
  </section>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
