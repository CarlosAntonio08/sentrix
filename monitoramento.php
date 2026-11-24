<?php
require_once 'includes/auth.php';

// --- Ação: ligar/desligar gravação de uma câmera (admin/operador) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_gravando'])) {
    csrf_verificar();
    exigir_permissao('operar');

    $camId = (int) $_POST['toggle_gravando'];
    $stmt = $pdo->prepare(
        'SELECT c.gravando, d.nome, d.status FROM cameras c JOIN dispositivos d ON d.id = c.dispositivo_id WHERE c.id = ?'
    );
    $stmt->execute([$camId]);
    $cam = $stmt->fetch();

    if (!$cam) {
        flash('erro', 'Câmera não encontrada.');
    } elseif (!$cam['gravando'] && $cam['status'] !== 'online') {
        flash('erro', "Não é possível iniciar a gravação: \"{$cam['nome']}\" não está online.");
    } else {
        $novo = $cam['gravando'] ? 0 : 1;
        $pdo->prepare('UPDATE cameras SET gravando = ? WHERE id = ?')->execute([$novo, $camId]);
        registrar_log($pdo, $usuarioLogado['id'], $novo ? 'gravacao_iniciada' : 'gravacao_parada',
            "Gravação " . ($novo ? 'iniciada' : 'parada') . " em {$cam['nome']}");
        flash('ok', ($novo ? 'Gravação iniciada' : 'Gravação parada') . " em \"{$cam['nome']}\".");
    }
    redirecionar('monitoramento.php');
}

$cameras = $pdo->query(
    "SELECT d.id AS dispositivo_id, d.nome, d.status, d.localizacao,
            c.id AS camera_id, c.modelo, c.tipo AS tipo_camera, c.resolucao, c.ip_address, c.gravando
     FROM dispositivos d
     JOIN cameras c ON c.dispositivo_id = d.id
     ORDER BY d.nome"
)->fetchAll();

$outros = $pdo->query(
    "SELECT id, nome, tipo, status, localizacao
     FROM dispositivos
     WHERE tipo IN ('sensor','alarme','outro')
     ORDER BY nome"
)->fetchAll();

$titulo = 'Monitoramento — Sentrix Guard Flow';
require_once 'includes/header.php';
?>

<main>
  <section class="page-hero page-hero-compact">
    <div class="page-hero-orb"></div>
    <div class="page-hero-inner">
      <span class="section-label reveal">// MÓDULO DE MONITORAMENTO</span>
      <h1 class="page-hero-title reveal reveal-delay-1">
        CÂMERAS
        <span class="accent">E SENSORES</span>
      </h1>
      <p class="page-hero-desc reveal reveal-delay-2">
        Status de todas as câmeras, sensores e alarmes cadastrados, com controle de gravação.
      </p>
    </div>
  </section>

  <section class="page-section">
    <div class="page-section-inner">
      <?= flash_exibir() ?>

      <div class="page-section-header">
        <span class="section-label">CÂMERAS</span>
        <h2 class="section-title">FEEDS<br><span class="accent">CADASTRADOS</span></h2>
      </div>

      <p class="msg msg-info">ℹ A transmissão de vídeo ao vivo (RTSP → navegador) está prevista como trabalho futuro. Nesta versão, o painel exibe o status e controla a gravação de cada câmera.</p>

      <?php if (!$cameras): ?>
        <p class="panel-empty">Nenhuma câmera cadastrada ainda. <a href="dispositivos.php?novo=1" class="panel-link">Cadastrar câmera →</a></p>
      <?php else: ?>
      <div class="cam-grid">
        <?php foreach ($cameras as $c): ?>
        <div class="cam-card">
          <div class="cam-preview cam-status-<?= e($c['status']) ?>">
            <span class="cam-live-dot"></span>
            <span class="cam-live-label"><?= $c['status'] === 'online' ? 'ONLINE' : strtoupper(e(rotulo('status_disp', $c['status']))) ?></span>
            <?php if ($c['gravando']): ?><span class="cam-rec">● REC</span><?php endif; ?>
          </div>
          <div class="cam-body">
            <h3 class="cam-name"><?= e($c['nome']) ?></h3>
            <p class="cam-meta"><?= e($c['modelo'] ?? '—') ?> · <?= e(strtoupper($c['tipo_camera'])) ?> · <?= e($c['resolucao']) ?></p>
            <p class="cam-meta cam-meta-dim"><?= e($c['localizacao'] ?? 'Local não definido') ?><?= $c['ip_address'] ? ' · ' . e($c['ip_address']) : '' ?></p>

            <?php if (pode('operar')): ?>
            <form method="POST" action="monitoramento.php">
              <?= csrf_campo() ?>
              <input type="hidden" name="toggle_gravando" value="<?= (int) $c['camera_id'] ?>">
              <button type="submit" class="btn-outline cam-toggle-btn <?= $c['gravando'] ? 'is-recording' : '' ?>">
                <?= $c['gravando'] ? '■ Parar gravação' : '● Iniciar gravação' ?>
              </button>
            </form>
            <?php else: ?>
              <p class="cam-meta cam-meta-dim"><?= $c['gravando'] ? 'Gravando' : 'Sem gravação' ?> · somente leitura</p>
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </section>

  <section class="page-section pt-0">
    <div class="page-section-inner">
      <div class="page-section-header">
        <span class="section-label">SENSORES &amp; ALARMES</span>
        <h2 class="section-title">OUTROS<br><span class="accent">DISPOSITIVOS</span></h2>
      </div>

      <?php if (!$outros): ?>
        <p class="panel-empty">Nenhum outro dispositivo cadastrado.</p>
      <?php else: ?>
      <div class="panel-block">
        <ul class="panel-device-list">
          <?php foreach ($outros as $d): ?>
          <li class="panel-device-item">
            <span class="device-dot device-dot-<?= e($d['status']) ?>"></span>
            <div class="panel-device-info">
              <strong><?= e($d['nome']) ?></strong>
              <span><?= e(rotulo('tipo_disp', $d['tipo'])) ?> · <?= e($d['localizacao'] ?? 'sem local') ?></span>
            </div>
            <span class="panel-device-status status-<?= e($d['status']) ?>"><?= e(rotulo('status_disp', $d['status'])) ?></span>
          </li>
          <?php endforeach; ?>
        </ul>
      </div>
      <?php endif; ?>
    </div>
  </section>
</main>

<?php require_once 'includes/footer.php'; ?>
