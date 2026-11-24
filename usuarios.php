<?php
/**
 * Administração de usuários (somente admin): alterar cargo, ativar/desativar
 * e redefinir senha. O admin não pode rebaixar nem desativar a própria conta,
 * para nunca ficar sem nenhum administrador.
 */
require_once __DIR__ . '/includes/auth.php';
exigir_permissao('gerenciar_usuarios');

$cargos = array_keys(ROTULOS['cargo']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verificar();
    $acao = $_POST['acao'] ?? '';
    $id   = (int) ($_POST['id'] ?? 0);

    $stmt = $pdo->prepare('SELECT id, nome, cargo, ativo FROM usuarios WHERE id = ?');
    $stmt->execute([$id]);
    $alvo = $stmt->fetch();

    if (!$alvo) {
        flash('erro', 'Usuário não encontrado.');
    } elseif ($id === $usuarioLogado['id'] && in_array($acao, ['cargo', 'status'], true)) {
        flash('erro', 'Você não pode alterar o cargo ou desativar a sua própria conta.');
    } elseif ($acao === 'cargo') {
        $novo = $_POST['cargo'] ?? '';
        if (in_array($novo, $cargos, true) && $novo !== $alvo['cargo']) {
            $pdo->prepare('UPDATE usuarios SET cargo = ? WHERE id = ?')->execute([$novo, $id]);
            registrar_log($pdo, $usuarioLogado['id'], 'usuario_cargo',
                "Cargo de {$alvo['nome']} (#{$id}) alterado de {$alvo['cargo']} para {$novo}");
            flash('ok', "Cargo de {$alvo['nome']} alterado para " . rotulo('cargo', $novo) . '.');
        }
    } elseif ($acao === 'status') {
        $novo = $alvo['ativo'] ? 0 : 1;
        $pdo->prepare('UPDATE usuarios SET ativo = ? WHERE id = ?')->execute([$novo, $id]);
        registrar_log($pdo, $usuarioLogado['id'], $novo ? 'usuario_ativado' : 'usuario_desativado',
            "Usuário {$alvo['nome']} (#{$id}) " . ($novo ? 'ativado' : 'desativado'));
        flash('ok', "Usuário {$alvo['nome']} " . ($novo ? 'ativado.' : 'desativado. Ele perde o acesso imediatamente.'));
    } elseif ($acao === 'senha') {
        $senha = $_POST['nova_senha'] ?? '';
        if (!senha_valida($senha)) {
            flash('erro', 'A nova senha deve ter no mínimo 8 caracteres, com letras e números.');
        } else {
            $pdo->prepare('UPDATE usuarios SET senha_hash = ? WHERE id = ?')
                ->execute([password_hash($senha, PASSWORD_DEFAULT), $id]);
            registrar_log($pdo, $usuarioLogado['id'], 'usuario_senha', "Senha de {$alvo['nome']} (#{$id}) redefinida");
            flash('ok', "Senha de {$alvo['nome']} redefinida.");
        }
    }
    redirecionar('usuarios.php');
}

$usuarios = $pdo->query(
    'SELECT id, nome, email, cargo, ativo, ultimo_login, criado_em FROM usuarios ORDER BY ativo DESC, nome'
)->fetchAll();

$titulo = 'Usuários — Sentrix Guard Flow';
require_once __DIR__ . '/includes/header.php';
?>

<main>
  <section class="page-hero page-hero-compact">
    <div class="page-hero-orb"></div>
    <div class="page-hero-inner">
      <span class="section-label">// ADMINISTRAÇÃO</span>
      <h1 class="page-hero-title">USUÁRIOS <span class="accent">E ACESSOS</span></h1>
      <p class="page-hero-desc">Defina quem pode visualizar, operar ou administrar o sistema.</p>
    </div>
  </section>

  <section class="page-section">
    <div class="page-section-inner">
      <?php require __DIR__ . '/includes/abas-admin.php'; ?>

      <?= flash_exibir() ?>

      <div class="cargos-legenda">
        <div><strong>Administrador</strong> — acesso total, gerencia usuários e vê o log de auditoria.</div>
        <div><strong>Operador</strong> — resolve alertas, controla gravação, cadastra dispositivos e cercas.</div>
        <div><strong>Visualizador</strong> — apenas consulta. É o cargo padrão de contas novas.</div>
      </div>

      <div class="panel-block">
        <div class="panel-table-wrap">
          <table class="panel-table">
            <thead>
              <tr><th>Nome</th><th>E-mail</th><th>Cargo</th><th>Situação</th><th>Último login</th><th class="col-acoes">Ações</th></tr>
            </thead>
            <tbody>
            <?php foreach ($usuarios as $u): $eu = (int) $u['id'] === $usuarioLogado['id']; ?>
              <tr class="<?= $u['ativo'] ? '' : 'linha-inativa' ?>">
                <td><strong class="td-destaque"><?= e($u['nome']) ?></strong><?= $eu ? ' <span class="tag-voce">você</span>' : '' ?></td>
                <td class="td-mono"><?= e($u['email']) ?></td>
                <td>
                  <?php if ($eu): ?>
                    <?= e(rotulo('cargo', $u['cargo'])) ?>
                  <?php else: ?>
                  <form method="POST" action="usuarios.php" class="inline-form">
                    <?= csrf_campo() ?>
                    <input type="hidden" name="acao" value="cargo">
                    <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                    <select name="cargo" class="form-input form-input-sm" data-autosubmit aria-label="Cargo de <?= e($u['nome']) ?>">
                      <?php foreach ($cargos as $c): ?>
                        <option value="<?= $c ?>" <?= $u['cargo'] === $c ? 'selected' : '' ?>><?= e(rotulo('cargo', $c)) ?></option>
                      <?php endforeach; ?>
                    </select>
                    <noscript><button class="btn-outline btn-xs" type="submit">OK</button></noscript>
                  </form>
                  <?php endif; ?>
                </td>
                <td><span class="status-tag status-<?= $u['ativo'] ? 'online' : 'offline' ?>"><?= $u['ativo'] ? 'Ativo' : 'Desativado' ?></span></td>
                <td class="td-mono"><?= $u['ultimo_login'] ? date('d/m/Y H:i', strtotime($u['ultimo_login'])) : 'nunca' ?></td>
                <td class="col-acoes">
                  <?php if (!$eu): ?>
                  <form method="POST" action="usuarios.php" class="inline-form">
                    <?= csrf_campo() ?>
                    <input type="hidden" name="acao" value="status">
                    <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                    <button type="submit" class="<?= $u['ativo'] ? 'btn-danger' : 'btn-outline' ?> btn-xs"><?= $u['ativo'] ? 'Desativar' : 'Ativar' ?></button>
                  </form>
                  <?php endif; ?>
                  <details class="inline-details">
                    <summary class="btn-outline btn-xs">Nova senha</summary>
                    <form method="POST" action="usuarios.php" class="details-form">
                      <?= csrf_campo() ?>
                      <input type="hidden" name="acao" value="senha">
                      <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                      <input type="password" name="nova_senha" class="form-input form-input-sm" minlength="8" placeholder="Nova senha" autocomplete="new-password" required>
                      <button type="submit" class="btn-primary btn-xs">Salvar</button>
                    </form>
                  </details>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </section>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
