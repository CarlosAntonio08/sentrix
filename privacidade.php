<?php
require_once __DIR__ . '/includes/bootstrap.php';
$titulo = 'Política de Privacidade — Sentrix Guard Flow';
require_once __DIR__ . '/includes/header.php';
?>

<main>
  <section class="page-hero page-hero-compact">
    <div class="page-hero-orb"></div>
    <div class="page-hero-inner">
      <span class="section-label">// LGPD</span>
      <h1 class="page-hero-title">POLÍTICA DE <span class="accent">PRIVACIDADE</span></h1>
      <p class="page-hero-desc">Como o Sentrix Guard Flow trata os dados pessoais, conforme a Lei nº 13.709/2018 (LGPD).</p>
    </div>
  </section>

  <section class="page-section">
    <div class="page-section-inner texto-legal">
      <p class="msg msg-info">ℹ Este é um projeto acadêmico (Trabalho de Conclusão de Curso). Os dados são usados somente para demonstrar o funcionamento do sistema.</p>

      <h2>1. Quais dados coletamos</h2>
      <ul>
        <li><strong>Lista de espera do aplicativo:</strong> apenas o e-mail informado, a data do consentimento e o endereço IP de origem.</li>
        <li><strong>Contas de usuário:</strong> nome, e-mail, senha (armazenada somente como hash criptográfico, nunca em texto puro), cargo e data do último acesso.</li>
        <li><strong>Login por e-mail:</strong> o e-mail da conta recebe um link de acesso. Guardamos o histórico de envios
          (destinatário, assunto, data e resultado) e apenas o código criptográfico (hash) de cada link, que expira em 15 minutos.</li>
        <li><strong>Registro de auditoria:</strong> ações realizadas no painel (login, alterações, resolução de alertas), com data, hora e IP, para segurança e rastreabilidade.</li>
        <li><strong>Dados de monitoramento:</strong> posições de GPS e alertas dos dispositivos cadastrados.</li>
      </ul>

      <h2>2. Para que usamos</h2>
      <ul>
        <li>E-mail da lista de espera: avisar sobre o lançamento do aplicativo (base legal: consentimento — art. 7º, I).</li>
        <li>Dados de conta e auditoria: permitir o acesso ao sistema e proteger contra uso indevido (base legal: execução de contrato e legítimo interesse — art. 7º, V e IX).</li>
      </ul>

      <h2>3. Compartilhamento</h2>
      <p>Os dados não são vendidos nem compartilhados com terceiros. O mapa utiliza imagens do OpenStreetMap, carregadas diretamente pelo seu navegador.</p>

      <h2>4. Seus direitos</h2>
      <p>Você pode solicitar a qualquer momento a confirmação, o acesso, a correção ou a exclusão dos seus dados, além de revogar o consentimento (art. 18 da LGPD), entrando em contato com a equipe do projeto.</p>

      <h2>5. Segurança</h2>
      <p>Senhas com hash (bcrypt), proteção contra SQL Injection, XSS e CSRF, limite de tentativas de login, controle de acesso por cargo e registro de auditoria.</p>

      <h2>6. Retenção</h2>
      <p>Os dados são mantidos apenas pelo período do projeto acadêmico e podem ser removidos ao final da avaliação.</p>
    </div>
  </section>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
