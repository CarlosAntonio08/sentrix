<?php
/**
 * Admin → E-mail: configura o Gmail usado para enviar os links de acesso,
 * envia um e-mail de teste e mostra o histórico de envios.
 * As configurações ficam em config/email.local.php (bloqueado para a web).
 */
require_once 'includes/auth.php';
require_once 'includes/email.php';
exigir_permissao('gerenciar_usuarios');

const ARQUIVO_EMAIL_LOCAL = __DIR__ . '/config/email.local.php';

function gravar_config_email(array $c): bool
{
    $linhas = ["<?php", "// Gerado em Admin > E-mail em " . date('d/m/Y H:i') . ". Pode editar ou apagar."];
    foreach ($c as $const => $valor) {
        $linhas[] = "define('{$const}', " . var_export($valor, true) . ");";
    }
    $ok = file_put_contents(ARQUIVO_EMAIL_LOCAL, implode("\n", $linhas) . "\n") !== false;
    // Sem isto, o cache do PHP (OPcache) pode continuar usando a versão antiga por alguns segundos
    if ($ok && function_exists('opcache_invalidate')) {
        opcache_invalidate(ARQUIVO_EMAIL_LOCAL, true);
    }
    return $ok;
}

$erros = [];
$form = [
    'usuario'   => MAIL_USUARIO,
    'remetente' => MAIL_REMETENTE_NOME,
    'host'      => MAIL_SMTP_HOST,
    'porta'     => (string) MAIL_SMTP_PORTA,
    'confirmar' => LOGIN_CONFIRMAR_EMAIL,
    'app_url'   => APP_URL,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verificar();
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'salvar') {
        $form = [
            'usuario'   => trim($_POST['usuario'] ?? ''),
            'remetente' => trim($_POST['remetente'] ?? '') ?: 'Sentrix Guard Flow',
            'host'      => trim($_POST['host'] ?? '') ?: 'smtp.gmail.com',
            'porta'     => trim($_POST['porta'] ?? '') ?: '587',
            'confirmar' => !empty($_POST['confirmar']),
            'app_url'   => trim($_POST['app_url'] ?? ''),
        ];
        // A senha de app do Google vem com espaços ("abcd efgh ijkl mnop"); eles são removidos
        $senhaNova = str_replace(' ', '', (string) ($_POST['senha'] ?? ''));
        $senha = $senhaNova !== '' ? $senhaNova : MAIL_SENHA;

        if ($form['usuario'] !== '' && !filter_var($form['usuario'], FILTER_VALIDATE_EMAIL)) $erros[] = 'Informe um e-mail válido.';
        if ($form['usuario'] !== '' && $senha === '') $erros[] = 'Informe a senha de app.';
        if (!ctype_digit($form['porta'])) $erros[] = 'A porta deve ser um número (Gmail: 587).';
        if ($form['app_url'] !== '' && !preg_match('#^https?://#', $form['app_url'])) $erros[] = 'O endereço do site deve começar com http:// ou https://';
        if (mb_strlen($form['remetente']) > 80) $erros[] = 'Nome do remetente muito longo.';

        if (!$erros) {
            $ok = gravar_config_email([
                'MAIL_SMTP_HOST'        => $form['host'],
                'MAIL_SMTP_PORTA'       => (int) $form['porta'],
                'MAIL_USUARIO'          => $form['usuario'],
                'MAIL_SENHA'            => $form['usuario'] !== '' ? $senha : '',
                'MAIL_REMETENTE_NOME'   => $form['remetente'],
                'LOGIN_CONFIRMAR_EMAIL' => $form['confirmar'],
                'APP_URL'               => $form['app_url'],
            ]);
            if ($ok) {
                registrar_log($pdo, $usuarioLogado['id'], 'email_configurado',
                    $form['usuario'] !== '' ? 'Envio de e-mail configurado (' . mascarar_email($form['usuario']) . ')' : 'Envio de e-mail em modo demonstração');
                flash('ok', 'Configurações salvas. Use "Enviar e-mail de teste" para conferir.');
            } else {
                flash('erro', 'Não foi possível gravar o arquivo config/email.local.php. Verifique as permissões da pasta.');
            }
            redirecionar('configuracoes-email.php');
        }
    }

    if ($acao === 'testar') {
        $html = modelo_email(
            'Teste de envio',
            'Se você está lendo isto, o Sentrix já consegue enviar e-mails. Os links de acesso vão chegar normalmente. ✓'
        );
        $r = enviar_email($pdo, $usuarioLogado['id'], $usuarioLogado['email'], 'Teste de envio — Sentrix', $html,
            'Se você está lendo isto, o Sentrix já consegue enviar e-mails.');
        if ($r['status'] === 'enviado') {
            flash('ok', 'E-mail de teste enviado para ' . $usuarioLogado['email'] . '. Confira a caixa de entrada (e o Spam).');
        } elseif ($r['status'] === 'demonstracao') {
            flash('info', 'Modo demonstração: o e-mail foi gerado, mas não saiu. Configure o Gmail acima para enviar de verdade.');
        } else {
            flash('erro', explicar_erro_email($r['erro']));
        }
        redirecionar('configuracoes-email.php');
    }
}

$historico = $pdo->query(
    'SELECT destinatario, assunto, status, erro, criado_em FROM emails_enviados ORDER BY id DESC LIMIT 20'
)->fetchAll();

$titulo = 'E-mail — Sentrix Guard Flow';
require_once 'includes/header.php';
?>

<main>
  <section class="page-hero page-hero-compact">
    <div class="page-hero-orb"></div>
    <div class="page-hero-inner">
      <span class="section-label">// ADMINISTRAÇÃO</span>
      <h1 class="page-hero-title">ENVIO DE <span class="accent">E-MAIL</span></h1>
      <p class="page-hero-desc">Conta usada para enviar os links de acesso do login.</p>
    </div>
  </section>

  <section class="page-section">
    <div class="page-section-inner">
      <?php require 'includes/abas-admin.php'; ?>
      <?= flash_exibir() ?>

      <?php if (email_configurado()): ?>
        <div class="status-card status-card-ok">
          <span class="device-dot device-dot-online"></span>
          <div><strong>Envio ativo</strong><p>Os e-mails saem por <?= e(MAIL_USUARIO) ?> (<?= e(MAIL_SMTP_HOST) ?>:<?= (int) MAIL_SMTP_PORTA ?>).</p></div>
        </div>
      <?php else: ?>
        <div class="status-card status-card-demo">
          <span class="device-dot device-dot-manutencao"></span>
          <div><strong>Modo demonstração</strong><p>Os e-mails são gerados, mas não saem: aparecem na caixa de saída durante o login.
            Configure um Gmail abaixo para enviar de verdade.</p></div>
        </div>
      <?php endif; ?>

      <?php if (!LOGIN_CONFIRMAR_EMAIL): ?>
        <p class="msg msg-info">ℹ A confirmação por e-mail no login está <strong>desligada</strong>: o acesso é liberado só com a senha.</p>
      <?php endif; ?>

      <div class="panel-grid">
        <div class="panel-block">
          <h2 class="block-title">Configurar</h2>
          <?php if ($erros): ?>
            <div class="msg msg-erro" role="alert"><ul><?php foreach ($erros as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
          <?php endif; ?>
          <form method="POST" action="configuracoes-email.php" class="form-stack">
            <?= csrf_campo() ?>
            <input type="hidden" name="acao" value="salvar">

            <label class="form-label" for="usuario">Gmail que vai enviar</label>
            <input class="form-input" id="usuario" name="usuario" type="email" value="<?= e($form['usuario']) ?>" placeholder="sentrix.tcc@gmail.com" autocomplete="off">

            <label class="form-label" for="senha">Senha de app (16 letras)</label>
            <input class="form-input" id="senha" name="senha" type="password" autocomplete="new-password"
                   placeholder="<?= MAIL_SENHA !== '' ? '•••••••••••••••• (salva — deixe em branco para manter)' : 'abcd efgh ijkl mnop' ?>">

            <label class="form-label" for="remetente">Nome do remetente</label>
            <input class="form-input" id="remetente" name="remetente" maxlength="80" value="<?= e($form['remetente']) ?>">

            <details class="instalador-avancado mt-28">
              <summary>Opções avançadas</summary>
              <div class="form-row">
                <div>
                  <label class="form-label" for="host">Servidor SMTP</label>
                  <input class="form-input" id="host" name="host" value="<?= e($form['host']) ?>">
                </div>
                <div>
                  <label class="form-label" for="porta">Porta</label>
                  <input class="form-input" id="porta" name="porta" inputmode="numeric" value="<?= e($form['porta']) ?>">
                </div>
              </div>
              <label class="form-label" for="app_url">Endereço do site nos links (opcional)</label>
              <input class="form-input" id="app_url" name="app_url" value="<?= e($form['app_url']) ?>" placeholder="https://sentrix.seudominio.com">
              <p class="form-hint">Em branco, o sistema usa o endereço atual (<?= e(url_base()) ?>).
                Preencha quando o site estiver publicado na internet.</p>
              <label class="form-check">
                <input type="checkbox" name="confirmar" value="1" <?= $form['confirmar'] ? 'checked' : '' ?>>
                <span>Exigir o link por e-mail para entrar (recomendado)</span>
              </label>
            </details>

            <button type="submit" class="btn-primary form-submit">Salvar</button>
          </form>
          <form method="POST" action="configuracoes-email.php" class="mt-28">
            <?= csrf_campo() ?>
            <input type="hidden" name="acao" value="testar">
            <button type="submit" class="btn-outline btn-block">Enviar e-mail de teste para <?= e($usuarioLogado['email']) ?></button>
          </form>
        </div>

        <div class="panel-block">
          <h2 class="block-title">Como criar a senha de app do Gmail</h2>
          <ol class="passos-gmail">
            <li>Use uma conta Gmail própria do projeto (ex.: <em>sentrix.tcc@gmail.com</em>).</li>
            <li>Ative a <strong>Verificação em duas etapas</strong> em
              <a href="https://myaccount.google.com/signinoptions/twosv" target="_blank" rel="noopener">myaccount.google.com/signinoptions/twosv</a>.</li>
            <li>Abra <a href="https://myaccount.google.com/apppasswords" target="_blank" rel="noopener">myaccount.google.com/apppasswords</a>,
              digite o nome <strong>Sentrix</strong> e clique em <strong>Criar</strong>.</li>
            <li>Copie a senha de <strong>16 letras</strong> que aparece e cole no campo ao lado.</li>
            <li>Clique em <strong>Salvar</strong> e depois em <strong>Enviar e-mail de teste</strong>.</li>
          </ol>
          <p class="form-hint">A senha normal do Gmail não funciona aqui: o Google exige a senha de app.
            Ela fica gravada só neste computador, em <code>config/email.local.php</code>.</p>
        </div>
      </div>

      <div class="panel-block mt-28">
        <h2 class="block-title">Últimos e-mails</h2>
        <?php if (!$historico): ?>
          <p class="panel-empty">Nenhum e-mail gerado ainda.</p>
        <?php else: ?>
        <div class="panel-table-wrap">
          <table class="panel-table">
            <thead><tr><th>Quando</th><th>Para</th><th>Assunto</th><th>Status</th></tr></thead>
            <tbody>
              <?php foreach ($historico as $h): ?>
              <tr>
                <td class="td-mono"><?= date('d/m/Y H:i:s', strtotime($h['criado_em'])) ?></td>
                <td class="td-mono"><?= e(mascarar_email($h['destinatario'])) ?></td>
                <td><?= e($h['assunto']) ?></td>
                <td><span class="pill pill-status-<?= e($h['status']) ?>" title="<?= e($h['erro'] ?? '') ?>">
                  <?= ['enviado' => 'Enviado', 'falhou' => 'Falhou', 'demonstracao' => 'Demonstração'][$h['status']] ?? e($h['status']) ?></span></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </section>
</main>

<?php require_once 'includes/footer.php'; ?>
