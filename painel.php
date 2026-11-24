<?php
require_once __DIR__ . '/includes/auth.php'; // exige login e carrega $usuarioLogado

$ind = indicadores_painel($pdo);

$alertasRecentes = $pdo->query(
    "SELECT a.id, a.tipo, a.nivel, a.status, a.criado_em, d.nome AS dispositivo
     FROM alertas a
     LEFT JOIN dispositivos d ON d.id = a.dispositivo_id
     ORDER BY a.criado_em DESC, a.id DESC
     LIMIT 6"
)->fetchAll();

$dispositivos = $pdo->query(
    "SELECT id, nome, tipo, status, localizacao FROM dispositivos
     ORDER BY FIELD(status, 'offline', 'manutencao', 'online'), nome LIMIT 8"
)->fetchAll();

$titulo = 'Painel — Sentrix Guard Flow';
$scriptsPagina = ['painel.js'];
require_once __DIR__ . '/includes/header.php';
?>

<main>
  <section class="page-hero panel-hero">
    <div class="page-hero-orb"></div>
    <div class="page-hero-inner">
      <span class="section-label reveal">// DASHBOARD · <?= e(rotulo('cargo', $usuarioLogado['cargo'])) ?></span>
      <h1 class="page-hero-title reveal reveal-delay-1">
        OLÁ, <span class="accent"><?= e(explode(' ', $usuarioLogado['nome'])[0]) ?></span>
      </h1>
      <p class="page-hero-desc reveal reveal-delay-2">
        Visão geral do sistema de segurança. Os indicadores se atualizam sozinhos a cada 15 segundos.
      </p>
    </div>
  </section>

  <section class="page-stats" aria-live="polite">
    <div class="page-stats-inner">
      <div class="stat-item">
        <span class="stat-value" data-indicador="total_dispositivos"><?= $ind['total_dispositivos'] ?></span>
        <span class="stat-label">Dispositivos totais</span>
      </div>
      <div class="stat-divider"></div>
      <div class="stat-item">
        <span class="stat-value" data-indicador="online"><?= $ind['online'] ?></span>
        <span class="stat-label">Online agora</span>
      </div>
      <div class="stat-divider"></div>
      <div class="stat-item">
        <span class="stat-value" data-indicador="alertas_novos"><?= $ind['alertas_novos'] ?></span>
        <span class="stat-label">Alertas novos</span>
      </div>
      <div class="stat-divider"></div>
      <div class="stat-item">
        <span class="stat-value stat-critico" data-indicador="criticos"><?= $ind['criticos'] ?></span>
        <span class="stat-label">Críticos em aberto</span>
      </div>
    </div>
  </section>

  <section class="page-section">
    <div class="page-section-inner">
      <?= flash_exibir() ?>

      <div class="sim-box app-box" data-se-navegador>
        <img src="assets/icons/icon-192.png" alt="">
        <div class="app-box-texto">
          <strong>Sentrix como aplicativo</strong>
          <p>Instale o painel no celular ou no computador para abrir com um toque, em tela cheia.</p>
        </div>
        <button type="button" class="btn-primary btn-sm" data-instalar-app hidden>⤓ Instalar</button>
        <a href="download.php" class="btn-outline btn-sm" data-instalar-manual>Como instalar</a>
      </div>

      <div class="aviso-novos" id="avisoNovos" hidden>
        <span>● Novos alertas chegaram.</span>
        <a href="painel.php" class="btn-primary btn-xs">Atualizar lista</a>
      </div>

      <?php if (pode('operar')): ?>
      <div class="sim-box">
        <div>
          <strong>Modo demonstração</strong>
          <p>Gera posições de GPS e alertas simulados (câmeras, sensores e cercas virtuais) para apresentar o sistema funcionando sem equipamentos físicos.</p>
        </div>
        <form method="POST" action="simulador.php">
          <?= csrf_campo() ?>
          <input type="hidden" name="voltar" value="painel.php">
          <button type="submit" class="btn-outline btn-sm">⚡ Gerar eventos simulados</button>
        </form>
      </div>
      <?php endif; ?>

      <div class="panel-grid">
        <div class="panel-block">
          <div class="panel-block-header">
            <h2 class="block-title">Alertas <span class="accent-inline">recentes</span></h2>
            <a href="alertas-ia.php" class="panel-link">Ver todos →</a>
          </div>

          <?php if (!$alertasRecentes): ?>
            <p class="panel-empty">Nenhum alerta registrado ainda.</p>
          <?php else: ?>
            <div class="panel-table-wrap">
              <table class="panel-table">
                <thead>
                  <tr><th>Nível</th><th>Tipo</th><th>Dispositivo</th><th>Status</th><th>Quando</th></tr>
                </thead>
                <tbody>
                  <?php foreach ($alertasRecentes as $a): ?>
                  <tr>
                    <td><span class="pill pill-<?= e($a['nivel']) ?>"><?= e(rotulo('nivel', $a['nivel'])) ?></span></td>
                    <td><?= e(rotulo('tipo_alerta', $a['tipo'])) ?></td>
                    <td><?= e($a['dispositivo'] ?? '—') ?></td>
                    <td><span class="pill pill-status-<?= e($a['status']) ?>"><?= e(rotulo('status_alerta', $a['status'])) ?></span></td>
                    <td class="td-mono"><?= date('d/m H:i', strtotime($a['criado_em'])) ?></td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>

        <div class="panel-block">
          <div class="panel-block-header">
            <h2 class="block-title">Dispositivos</h2>
            <a href="dispositivos.php" class="panel-link">Gerenciar →</a>
          </div>

          <?php if (!$dispositivos): ?>
            <p class="panel-empty">Nenhum dispositivo cadastrado ainda.</p>
          <?php else: ?>
            <ul class="panel-device-list">
              <?php foreach ($dispositivos as $d): ?>
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
          <?php endif; ?>
        </div>
      </div>
    </div>
  </section>

  <section class="page-section pt-0">
    <div class="page-section-inner">
      <div class="page-section-header">
        <span class="section-label">NAVEGAÇÃO RÁPIDA</span>
        <h2 class="section-title">MÓDULOS<br><span class="accent">DO SISTEMA</span></h2>
      </div>
      <div class="cards-grid cards-grid-4">
        <a href="monitoramento.php" class="card">
          <div class="card-icon"></div>
          <h3 class="card-title">Monitoramento</h3>
          <p class="card-desc">Câmeras, sensores e controle de gravação.</p>
        </a>
        <a href="alertas-ia.php" class="card">
          <div class="card-icon"></div>
          <h3 class="card-title">Alertas IA</h3>
          <p class="card-desc">Acompanhe, resolva e exporte alertas.</p>
        </a>
        <a href="gps.php" class="card">
          <div class="card-icon"></div>
          <h3 class="card-title">GPS</h3>
          <p class="card-desc">Mapa ao vivo, histórico e cercas virtuais.</p>
        </a>
        <?php if (pode('gerenciar_usuarios')): ?>
        <a href="usuarios.php" class="card">
          <div class="card-icon"></div>
          <h3 class="card-title">Administração</h3>
          <p class="card-desc">Usuários, cargos e log de auditoria.</p>
        </a>
        <?php else: ?>
        <a href="dispositivos.php" class="card">
          <div class="card-icon"></div>
          <h3 class="card-title">Dispositivos</h3>
          <p class="card-desc">Lista completa de equipamentos.</p>
        </a>
        <?php endif; ?>
      </div>
    </div>
  </section>
</main>

<script type="application/json" id="dadosPainel"><?= json_encode(['ultimo_alerta_id' => $ind['ultimo_alerta_id']]) ?></script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
