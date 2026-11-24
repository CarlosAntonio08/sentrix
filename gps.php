<?php
require_once __DIR__ . '/includes/auth.php';

$rastreadores = $pdo->query(
    "SELECT g.id, g.nome, g.tipo, g.ativo, l.velocidade_kmh, l.registrado_em
     FROM gps_dispositivos g
     LEFT JOIN gps_localizacoes l ON l.id = (
         SELECT id FROM gps_localizacoes WHERE gps_dispositivo_id = g.id
         ORDER BY registrado_em DESC, id DESC LIMIT 1)
     ORDER BY g.nome"
)->fetchAll();

$historico = $pdo->query(
    "SELECT l.latitude, l.longitude, l.velocidade_kmh, l.registrado_em, g.nome
     FROM gps_localizacoes l
     JOIN gps_dispositivos g ON g.id = l.gps_dispositivo_id
     ORDER BY l.registrado_em DESC, l.id DESC
     LIMIT 30"
)->fetchAll();

$cercas = $pdo->query(
    'SELECT nome, centro_lat, centro_lng, raio_metros, ativa FROM cercas_virtuais ORDER BY nome'
)->fetchAll();

$dadosMapa = [
    'cercas' => array_map(fn ($c) => [
        'nome'  => $c['nome'],
        'lat'   => (float) $c['centro_lat'],
        'lng'   => (float) $c['centro_lng'],
        'raio'  => (int) $c['raio_metros'],
        'ativa' => (bool) $c['ativa'],
    ], $cercas),
    'intervalo' => 10000, // atualiza o mapa a cada 10 s
];

$titulo   = 'GPS — Sentrix Guard Flow';
$usarMapa = true;
$scriptsPagina = ['gps.js'];
require_once __DIR__ . '/includes/header.php';
?>

<main>
  <section class="page-hero page-hero-compact">
    <div class="page-hero-orb"></div>
    <div class="page-hero-inner">
      <span class="section-label reveal">// RASTREAMENTO</span>
      <h1 class="page-hero-title reveal reveal-delay-1">
        LOCALIZAÇÃO
        <span class="accent">DOS RASTREADORES</span>
      </h1>
      <p class="page-hero-desc reveal reveal-delay-2">
        Veículos, pessoas e ativos no mapa, com trilha recente, histórico de posições e cercas virtuais.
      </p>
    </div>
  </section>

  <section class="page-section">
    <div class="page-section-inner">
      <nav class="tabs" aria-label="GPS">
        <a href="gps.php" class="tab active">Mapa e histórico</a>
        <a href="cercas.php" class="tab">Cercas virtuais</a>
      </nav>

      <?= flash_exibir() ?>

      <div class="map-toolbar">
        <span class="map-status" id="mapaStatus" aria-live="polite">Carregando posições…</span>
        <?php if (pode('operar')): ?>
        <form method="POST" action="simulador.php" class="inline-form">
          <?= csrf_campo() ?>
          <input type="hidden" name="voltar" value="gps.php">
          <button type="submit" class="btn-outline btn-xs">⚡ Simular movimento</button>
        </form>
        <?php endif; ?>
      </div>
      <div id="gpsMap" class="gps-map gps-map-grande"></div>
      <p class="panel-empty" id="mapaVazio" hidden>Nenhuma posição GPS registrada ainda.</p>
    </div>
  </section>

  <section class="page-section pt-0">
    <div class="page-section-inner panel-grid">
      <div class="panel-block">
        <div class="panel-block-header">
          <h2 class="block-title">Rastreadores</h2>
          <a href="dispositivos.php?tipo=gps" class="panel-link">Gerenciar →</a>
        </div>
        <?php if (!$rastreadores): ?>
          <p class="panel-empty">Nenhum rastreador cadastrado.</p>
        <?php else: ?>
          <ul class="panel-device-list">
            <?php foreach ($rastreadores as $d): ?>
            <li class="panel-device-item">
              <span class="device-dot device-dot-<?= $d['ativo'] ? 'online' : 'offline' ?>"></span>
              <div class="panel-device-info">
                <strong><?= e($d['nome']) ?></strong>
                <span>
                  <?= e(rotulo('tipo_gps', $d['tipo'])) ?>
                  <?= $d['registrado_em'] !== null ? '· ' . number_format((float) $d['velocidade_kmh'], 1, ',', '') . ' km/h · ' . date('d/m H:i', strtotime($d['registrado_em'])) : '· sem posição' ?>
                </span>
              </div>
              <span class="panel-device-status status-<?= $d['ativo'] ? 'online' : 'offline' ?>"><?= $d['ativo'] ? 'Ativo' : 'Inativo' ?></span>
            </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>

      <div class="panel-block">
        <div class="panel-block-header">
          <h2 class="block-title">Cercas virtuais</h2>
          <a href="cercas.php" class="panel-link">Gerenciar →</a>
        </div>
        <?php if (!$cercas): ?>
          <p class="panel-empty">Nenhuma cerca virtual configurada.</p>
        <?php else: ?>
          <ul class="panel-device-list">
            <?php foreach ($cercas as $c): ?>
            <li class="panel-device-item">
              <span class="device-dot device-dot-<?= $c['ativa'] ? 'online' : 'offline' ?>"></span>
              <div class="panel-device-info">
                <strong><?= e($c['nome']) ?></strong>
                <span>Raio de <?= (int) $c['raio_metros'] ?> m</span>
              </div>
              <span class="panel-device-status status-<?= $c['ativa'] ? 'online' : 'offline' ?>"><?= $c['ativa'] ? 'Ativa' : 'Inativa' ?></span>
            </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <section class="page-section pt-0">
    <div class="page-section-inner">
      <div class="page-section-header">
        <span class="section-label">HISTÓRICO</span>
        <h2 class="section-title">ÚLTIMAS<br><span class="accent">POSIÇÕES</span></h2>
      </div>
      <?php if (!$historico): ?>
        <p class="panel-empty">Sem histórico de localização.</p>
      <?php else: ?>
      <div class="panel-block">
        <div class="panel-table-wrap">
          <table class="panel-table">
            <thead><tr><th>Rastreador</th><th>Latitude</th><th>Longitude</th><th>Velocidade</th><th>Quando</th></tr></thead>
            <tbody>
              <?php foreach ($historico as $h): ?>
              <tr>
                <td><?= e($h['nome']) ?></td>
                <td class="td-mono"><?= number_format((float) $h['latitude'], 5) ?></td>
                <td class="td-mono"><?= number_format((float) $h['longitude'], 5) ?></td>
                <td><?= number_format((float) $h['velocidade_kmh'], 1, ',', '') ?> km/h</td>
                <td class="td-mono"><?= date('d/m H:i:s', strtotime($h['registrado_em'])) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </section>
</main>

<script type="application/json" id="dadosMapa"><?= json_encode($dadosMapa, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
