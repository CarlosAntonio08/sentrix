<?php
/**
 * Log de auditoria (somente admin): quem fez o quê, quando e de onde.
 */
require_once 'includes/auth.php';
exigir_permissao('ver_logs');

$porPagina = 50;
$pagina    = max(1, (int) ($_GET['pagina'] ?? 1));
$filtroTipo    = $_GET['tipo'] ?? '';
$filtroUsuario = (int) ($_GET['usuario'] ?? 0);

$tiposExistentes = $pdo->query('SELECT DISTINCT tipo_evento FROM logs_eventos ORDER BY tipo_evento')->fetchAll(PDO::FETCH_COLUMN);
$usuariosLista   = $pdo->query('SELECT id, nome FROM usuarios ORDER BY nome')->fetchAll();

$where = [];
$params = [];
if (in_array($filtroTipo, $tiposExistentes, true)) { $where[] = 'l.tipo_evento = ?'; $params[] = $filtroTipo; }
if ($filtroUsuario) { $where[] = 'l.usuario_id = ?'; $params[] = $filtroUsuario; }
$sqlWhere = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $pdo->prepare("SELECT COUNT(*) FROM logs_eventos l {$sqlWhere}");
$stmt->execute($params);
$total = (int) $stmt->fetchColumn();
$totalPaginas = max(1, (int) ceil($total / $porPagina));
$pagina = min($pagina, $totalPaginas);
$offset = ($pagina - 1) * $porPagina;

$stmt = $pdo->prepare(
    "SELECT l.id, l.tipo_evento, l.descricao, l.ip_origem, l.criado_em, u.nome AS usuario
     FROM logs_eventos l
     LEFT JOIN usuarios u ON u.id = l.usuario_id
     {$sqlWhere}
     ORDER BY l.criado_em DESC, l.id DESC
     LIMIT {$porPagina} OFFSET {$offset}"
);
$stmt->execute($params);
$logs = $stmt->fetchAll();

$linkPagina = function (int $p) use ($filtroTipo, $filtroUsuario): string {
    return 'logs.php?' . http_build_query(array_filter(['tipo' => $filtroTipo, 'usuario' => $filtroUsuario ?: null, 'pagina' => $p]));
};

$titulo = 'Log de auditoria — Sentrix Guard Flow';
require_once 'includes/header.php';
?>

<main>
  <section class="page-hero page-hero-compact">
    <div class="page-hero-orb"></div>
    <div class="page-hero-inner">
      <span class="section-label">// ADMINISTRAÇÃO</span>
      <h1 class="page-hero-title">LOG DE <span class="accent">AUDITORIA</span></h1>
      <p class="page-hero-desc">Registro de logins, alterações e ações realizadas no sistema.</p>
    </div>
  </section>

  <section class="page-section">
    <div class="page-section-inner">
      <?php require 'includes/abas-admin.php'; ?>

      <div class="toolbar">
        <form method="GET" action="logs.php" class="toolbar-filtros">
          <select name="tipo" class="form-input form-input-sm" aria-label="Tipo de evento">
            <option value="">Todos os eventos</option>
            <?php foreach ($tiposExistentes as $t): ?>
              <option value="<?= e($t) ?>" <?= $filtroTipo === $t ? 'selected' : '' ?>><?= e($t) ?></option>
            <?php endforeach; ?>
          </select>
          <select name="usuario" class="form-input form-input-sm" aria-label="Usuário">
            <option value="">Todos os usuários</option>
            <?php foreach ($usuariosLista as $u): ?>
              <option value="<?= (int) $u['id'] ?>" <?= $filtroUsuario === (int) $u['id'] ? 'selected' : '' ?>><?= e($u['nome']) ?></option>
            <?php endforeach; ?>
          </select>
          <button class="btn-outline btn-sm" type="submit">Filtrar</button>
        </form>
        <span class="toolbar-info"><?= $total ?> registro(s)</span>
      </div>

      <div class="panel-block">
        <?php if (!$logs): ?>
          <p class="panel-empty">Nenhum registro encontrado.</p>
        <?php else: ?>
        <div class="panel-table-wrap">
          <table class="panel-table">
            <thead><tr><th>Quando</th><th>Usuário</th><th>Evento</th><th>Descrição</th><th>IP</th></tr></thead>
            <tbody>
              <?php foreach ($logs as $l): ?>
              <tr>
                <td class="td-mono"><?= date('d/m/Y H:i:s', strtotime($l['criado_em'])) ?></td>
                <td><?= e($l['usuario'] ?? '—') ?></td>
                <td><span class="tag-evento <?= str_contains($l['tipo_evento'], 'falha') ? 'tag-evento-falha' : '' ?>"><?= e($l['tipo_evento']) ?></span></td>
                <td><?= e($l['descricao'] ?? '') ?></td>
                <td class="td-mono"><?= e($l['ip_origem'] ?? '') ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </div>

      <?php if ($totalPaginas > 1): ?>
      <nav class="paginacao" aria-label="Paginação">
        <?php if ($pagina > 1): ?><a class="btn-outline btn-xs" href="<?= e($linkPagina($pagina - 1)) ?>">← Anterior</a><?php endif; ?>
        <span>Página <?= $pagina ?> de <?= $totalPaginas ?></span>
        <?php if ($pagina < $totalPaginas): ?><a class="btn-outline btn-xs" href="<?= e($linkPagina($pagina + 1)) ?>">Próxima →</a><?php endif; ?>
      </nav>
      <?php endif; ?>
    </div>
  </section>
</main>

<?php require_once 'includes/footer.php'; ?>
