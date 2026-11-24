<?php
require_once __DIR__ . '/includes/bootstrap.php';
$titulo = 'Aplicativo — Sentrix Guard Flow';
$scriptsPagina = ['download.js'];
require_once __DIR__ . '/includes/header.php';

$seguro = getenv('RENDER') === 'true' || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || in_array($_SERVER['SERVER_NAME'] ?? '', ['localhost', '127.0.0.1'], true);
?>

<main>
  <section class="page-hero page-hero-compact">
    <div class="page-hero-orb"></div>
    <div class="page-hero-inner app-hero">
      <div>
        <span class="section-label reveal">// APLICATIVO</span>
        <h1 class="page-hero-title reveal reveal-delay-1">SENTRIX <span class="accent">NO SEU CELULAR</span></h1>
        <p class="page-hero-desc reveal reveal-delay-2">
          Instale o Sentrix como aplicativo: ícone na tela inicial, abre em tela cheia e
          sem barra do navegador. Não precisa de loja de aplicativos.
        </p>
        <div class="hero-btns reveal reveal-delay-3">
          <button type="button" class="btn-primary" data-instalar-app hidden>⤓ Instalar o aplicativo</button>
          <p class="msg msg-ok" data-app-instalado hidden>✓ Aplicativo instalado! Procure o ícone do Sentrix.</p>
          <a href="#como-instalar" class="btn-outline" data-instalar-manual>Como instalar</a>
        </div>
      </div>
      <img src="assets/icons/icon-512.png" alt="Ícone do aplicativo Sentrix" class="app-icone-grande reveal" width="220" height="220">
    </div>
  </section>

  <section class="page-section" id="como-instalar">
    <div class="page-section-inner">
      <div class="page-section-header">
        <span class="section-label">PASSO A PASSO</span>
        <h2 class="section-title">COMO <span class="accent">INSTALAR</span></h2>
      </div>

      <?php if (!$seguro): ?>
        <p class="msg msg-info">ℹ Para instalar, o site precisa estar em um endereço seguro (<strong>https://</strong>).
          No computador onde o sistema roda (INICIAR.bat), funciona direto. No celular, publique o site com https.</p>
      <?php endif; ?>

      <div class="app-passos">
        <div class="panel-block">
          <h3 class="block-title">Android</h3>
          <ol class="passos-gmail">
            <li>Abra o site no <strong>Chrome</strong>.</li>
            <li>Toque no menu <strong>⋮</strong> (canto superior direito).</li>
            <li>Escolha <strong>Instalar app</strong> (ou "Adicionar à tela inicial").</li>
          </ol>
        </div>
        <div class="panel-block">
          <h3 class="block-title">iPhone</h3>
          <ol class="passos-gmail">
            <li>Abra o site no <strong>Safari</strong>.</li>
            <li>Toque em <strong>Compartilhar</strong> (quadrado com seta para cima).</li>
            <li>Escolha <strong>Adicionar à Tela de Início</strong>.</li>
          </ol>
        </div>
        <div class="panel-block">
          <h3 class="block-title">Computador</h3>
          <ol class="passos-gmail">
            <li>Abra o site no <strong>Chrome</strong> ou no <strong>Edge</strong>.</li>
            <li>Clique no ícone <strong>⊕ Instalar</strong> na barra de endereço.</li>
            <li>Confirme em <strong>Instalar</strong>.</li>
          </ol>
        </div>
      </div>

      <div class="panel-block mt-28 app-fluxo">
        <h3 class="block-title">Como você entra no app</h3>
        <ol class="app-fluxo-passos">
          <li><strong>E-mail e senha</strong><span>Na tela de login do Sentrix.</span></li>
          <li><strong>Link no seu e-mail</strong><span>Enviamos um link de acesso, válido por <?= (int) LOGIN_LINK_MINUTOS ?> minutos.</span></li>
          <li><strong>Você está dentro</strong><span>O link abre o Sentrix no aparelho onde você tocou nele.</span></li>
        </ol>
        <a href="login.php" class="btn-primary">Entrar agora</a>
      </div>
    </div>
  </section>

  <section class="page-section pt-0">
    <div class="page-section-inner download-inner">
      <p class="section-label">NOVIDADES</p>
      <h2 class="section-title">Notificações <span class="accent">em breve</span></h2>
      <p class="sobre-text">Estamos trabalhando nos alertas por notificação no celular. Deixe seu e-mail para ser avisado.</p>

      <form id="leadForm" class="lead-form" novalidate>
        <?= csrf_campo() ?>
        <!-- Campo-armadilha contra robôs de spam: pessoas não o veem nem preenchem -->
        <div class="hp-field" aria-hidden="true">
          <label for="site">Não preencha</label>
          <input type="text" id="site" name="site" tabindex="-1" autocomplete="off">
        </div>
        <div class="lead-row">
          <label for="emailInput" class="sr-only">Seu e-mail</label>
          <input type="email" name="email" id="emailInput" class="form-input" placeholder="Seu e-mail" maxlength="160" required>
          <button class="btn-primary" id="submitBtn" type="submit">Avisar-me</button>
        </div>
        <label class="form-check lead-check">
          <input type="checkbox" name="consentimento" id="consentimento" value="1" required>
          <span>Concordo em receber e-mails sobre novidades e li a <a href="privacidade.php" target="_blank">Política de Privacidade</a>.</span>
        </label>
      </form>
      <p id="msg" class="lead-msg" role="status" hidden></p>
    </div>
  </section>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
