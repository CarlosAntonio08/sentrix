<?php
require_once 'includes/auth.php';

// --- Ação: atualizar status de um alerta (admin/operador) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['alerta_id'], $_POST['novo_status'])) {
    csrf_verificar();
    exigir_permissao('operar');

    $id = (int) $_POST['alerta_id'];
    $novoStatus = $_POST['novo_status'];

    if (array_key_exists($novoStatus, ROTULOS['status_alerta'])) {
        $upd = $pdo->prepare('UPDATE alertas SET status = ? WHERE id = ?');
        $upd->execute([$novoStatus, $id]);
        if ($upd->rowCount()) {
            registrar_log($pdo, $usuarioLogado['id'], 'alerta_' . $novoStatus, "Alerta #{$id} marcado como {$novoStatus}");
            flash('ok', "Alerta #{$id} marcado como " . mb_strtolower(rotulo('status_alerta', $novoStatus)) . '.');
        }
    }
    $voltar = in_array($_POST['filtro'] ?? '', array_keys(ROTULOS['status_alerta']), true) ? '?status=' . $_POST['filtro'] : '';
    redirecionar('alertas-ia.php' . $voltar);
}

// --- Filtros ---
$filtro = $_GET['status'] ?? 'todos';
$filtroNivel = $_GET['nivel'] ?? '';
$where  = [];
$params = [];
if (array_key_exists($filtro, ROTULOS['status_alerta'])) { $where[] = 'a.status = ?'; $params[] = $filtro; }
if (array_key_exists($filtroNivel, ROTULOS['nivel']))   { $where[] = 'a.nivel = ?';  $params[] = $filtroNivel; }

$stmt = $pdo->prepare(
    "SELECT a.id, a.tipo, a.nivel, a.descricao, a.status, a.criado_em, d.nome AS dispositivo,
            di.tipo_deteccao, di.confianca
     FROM alertas a
     LEFT JOIN dispositivos d ON d.id = a.dispositivo_id
     LEFT JOIN deteccoes_ia di ON di.alerta_id = a.id
     " . ($where ? 'WHERE ' . implode(' AND ', $where) : '') . "
     ORDER BY a.criado_em DESC, a.id DESC
     LIMIT 100"
);
$stmt->execute($params);
$alertas = $stmt->fetchAll();

$link = fn (array $q) => 'alertas-ia.php' . (($q = array_filter($q)) ? '?' . http_build_query($q) : '');

$titulo = 'Alertas — Sentrix Guard Flow';
require_once 'includes/header.php';
?>

<main>
  <section class="page-hero page-hero-compact">
    <div class="page-hero-orb"></div>
    <div class="page-hero-inner">
      <span class="section-label reveal">// ALERTAS E DETECÇÕES</span>
      <h1 class="page-hero-title reveal reveal-delay-1">
        ALERTAS
        <span class="accent">INTELIGENTES</span>
      </h1>
      <p class="page-hero-desc reveal reveal-delay-2">
        Eventos de câmeras, sensores e rastreadores, com nível de criticidade e — quando gerados por IA —
        o tipo de detecção e a confiança.
      </p>
    </div>
  </section>

  <section class="page-section">
    <div class="page-section-inner">
      <?= flash_exibir() ?>

      <div class="alerts-header">
        <div>
          <span class="section-label">GERENCIAR</span>
          <h2 class="section-title">TODOS OS<br><span class="accent">ALERTAS</span></h2>
        </div>
        <div class="alerts-tools">
          <div class="alerts-filters" aria-label="Filtrar por status">
            <a href="<?= e($link(['nivel' => $filtroNivel])) ?>" class="filter-pill <?= !array_key_exists($filtro, ROTULOS['status_alerta']) ? 'active' : '' ?>">Todos</a>
            <?php foreach (ROTULOS['status_alerta'] as $s => $r): ?>
              <a href="<?= e($link(['status' => $s, 'nivel' => $filtroNivel])) ?>" class="filter-pill <?= $filtro === $s ? 'active' : '' ?>"><?= e($r) ?>s</a>
            <?php endforeach; ?>
          </div>
          <div class="alerts-filters" aria-label="Filtrar por nível">
            <a href="<?= e($link(['status' => $filtro !== 'todos' ? $filtro : ''])) ?>" class="filter-pill <?= $filtroNivel === '' ? 'active' : '' ?>">Qualquer nível</a>
            <?php foreach (ROTULOS['nivel'] as $n => $r): ?>
              <a href="<?= e($link(['status' => $filtro !== 'todos' ? $filtro : '', 'nivel' => $n])) ?>" class="filter-pill <?= $filtroNivel === $n ? 'active' : '' ?>"><?= e($r) ?></a>
            <?php endforeach; ?>
          </div>
          <div class="alerts-filters">
            <a href="exportar-alertas.php<?= array_key_exists($filtro, ROTULOS['status_alerta']) ? '?status=' . e($filtro) : '' ?>" class="btn-outline btn-xs">⤓ Exportar CSV</a>
            <?php if (pode('operar')): ?>
            <form method="POST" action="simulador.php" class="inline-form">
              <?= csrf_campo() ?>
              <input type="hidden" name="voltar" value="alertas-ia.php">
              <button type="submit" class="btn-outline btn-xs">⚡ Simular evento</button>
            </form>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <?php if (!$alertas): ?>
        <p class="panel-empty">Nenhum alerta encontrado para este filtro.</p>
      <?php else: ?>
      <div class="alert-list">
        <?php foreach ($alertas as $a): ?>
        <div class="alert-item alert-item-<?= e($a['nivel']) ?>">
          <div class="alert-item-main">
            <span class="pill pill-<?= e($a['nivel']) ?>"><?= e(rotulo('nivel', $a['nivel'])) ?></span>
            <div>
              <strong class="alert-item-title">#<?= (int) $a['id'] ?> · <?= e(rotulo('tipo_alerta', $a['tipo'])) ?></strong>
              <p class="alert-item-desc"><?= e($a['descricao'] ?? 'Sem descrição.') ?></p>
              <p class="alert-item-meta">
                <?= e($a['dispositivo'] ?? 'Dispositivo removido') ?>
                <?php if ($a['tipo_deteccao']): ?>
                  · IA: <?= e(rotulo('deteccao', $a['tipo_deteccao'])) ?> (<?= number_format((float) $a['confianca'], 1, ',', '') ?>%)
                <?php endif; ?>
                · <?= date('d/m/Y H:i', strtotime($a['criado_em'])) ?>
              </p>
            </div>
          </div>

          <div class="alert-item-actions">
            <span class="pill pill-status-<?= e($a['status']) ?>"><?= e(rotulo('status_alerta', $a['status'])) ?></span>
            <?php if (pode('operar')): ?>
            <form method="POST" action="alertas-ia.php" class="inline-form">
              <?= csrf_campo() ?>
              <input type="hidden" name="alerta_id" value="<?= (int) $a['id'] ?>">
              <input type="hidden" name="filtro" value="<?= e($filtro) ?>">
              <?php if ($a['status'] === 'novo'): ?>
                <button type="submit" name="novo_status" value="visualizado" class="btn-outline btn-xs">Marcar visualizado</button>
              <?php endif; ?>
              <?php if ($a['status'] !== 'resolvido'): ?>
                <button type="submit" name="novo_status" value="resolvido" class="btn-primary btn-xs">Resolver</button>
              <?php else: ?>
                <button type="submit" name="novo_status" value="novo" class="btn-outline btn-xs">Reabrir</button>
              <?php endif; ?>
            </form>
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </section>
</main>

<?php require_once 'includes/footer.php'; ?>
