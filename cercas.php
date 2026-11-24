<?php
/**
 * Cercas virtuais (geofencing): áreas circulares no mapa. Quando um rastreador
 * sai de uma cerca ativa, o sistema gera um alerta "Saída de cerca virtual".
 */
require_once 'includes/auth.php';

$rastreadores = $pdo->query('SELECT id, nome FROM gps_dispositivos ORDER BY nome')->fetchAll();
$idsRastreadores = array_map('intval', array_column($rastreadores, 'id'));
$erros = [];
$form = ['nome' => '', 'gps_dispositivo_id' => '', 'centro_lat' => '', 'centro_lng' => '', 'raio_metros' => 300];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verificar();
    $acao = $_POST['acao'] ?? '';
    $id   = (int) ($_POST['id'] ?? 0);

    if ($acao === 'criar') {
        exigir_permissao('gerenciar_disp');
        $form = [
            'nome'               => trim($_POST['nome'] ?? ''),
            'gps_dispositivo_id' => (int) ($_POST['gps_dispositivo_id'] ?? 0),
            'centro_lat'         => trim($_POST['centro_lat'] ?? ''),
            'centro_lng'         => trim($_POST['centro_lng'] ?? ''),
            'raio_metros'        => (int) ($_POST['raio_metros'] ?? 0),
        ];
        if ($form['nome'] === '' || mb_strlen($form['nome']) > 120) $erros[] = 'Informe um nome (até 120 caracteres).';
        if (!coordenada_valida($form['centro_lat'], $form['centro_lng'])) $erros[] = 'Clique no mapa ou informe latitude/longitude válidas.';
        if ($form['raio_metros'] < 20 || $form['raio_metros'] > 50000) $erros[] = 'O raio deve ficar entre 20 e 50.000 metros.';
        if ($form['gps_dispositivo_id'] && !in_array($form['gps_dispositivo_id'], $idsRastreadores, true)) $erros[] = 'Rastreador inválido.';

        if (!$erros) {
            $pdo->prepare('INSERT INTO cercas_virtuais (nome, gps_dispositivo_id, centro_lat, centro_lng, raio_metros) VALUES (?, ?, ?, ?, ?)')
                ->execute([$form['nome'], $form['gps_dispositivo_id'] ?: null, $form['centro_lat'], $form['centro_lng'], $form['raio_metros']]);
            registrar_log($pdo, $usuarioLogado['id'], 'cerca_criada', "Cerca \"{$form['nome']}\" criada (raio {$form['raio_metros']} m)");
            flash('ok', "Cerca \"{$form['nome']}\" criada.");
            redirecionar('cercas.php');
        }
    } elseif ($acao === 'alternar') {
        exigir_permissao('gerenciar_disp');
        $pdo->prepare('UPDATE cercas_virtuais SET ativa = 1 - ativa WHERE id = ?')->execute([$id]);
        registrar_log($pdo, $usuarioLogado['id'], 'cerca_alterada', "Cerca #{$id} ativada/desativada");
        flash('ok', 'Situação da cerca atualizada.');
        redirecionar('cercas.php');
    } elseif ($acao === 'excluir') {
        exigir_permissao('excluir_disp');
        $pdo->prepare('DELETE FROM cercas_virtuais WHERE id = ?')->execute([$id]);
        registrar_log($pdo, $usuarioLogado['id'], 'cerca_excluida', "Cerca #{$id} excluída");
        flash('ok', 'Cerca excluída.');
        redirecionar('cercas.php');
    }
}

$cercas = $pdo->query(
    'SELECT c.id, c.nome, c.centro_lat, c.centro_lng, c.raio_metros, c.ativa, g.nome AS rastreador
     FROM cercas_virtuais c LEFT JOIN gps_dispositivos g ON g.id = c.gps_dispositivo_id
     ORDER BY c.nome'
)->fetchAll();

// Centro inicial do mapa: última posição conhecida ou centro da primeira cerca
$centro = $pdo->query('SELECT latitude AS lat, longitude AS lng FROM gps_localizacoes ORDER BY registrado_em DESC LIMIT 1')->fetch()
    ?: ($cercas ? ['lat' => $cercas[0]['centro_lat'], 'lng' => $cercas[0]['centro_lng']] : ['lat' => -22.9068, 'lng' => -43.1729]);

$dadosMapa = [
    'centro' => ['lat' => (float) $centro['lat'], 'lng' => (float) $centro['lng']],
    'cercas' => array_map(fn ($c) => [
        'nome' => $c['nome'], 'lat' => (float) $c['centro_lat'], 'lng' => (float) $c['centro_lng'],
        'raio' => (int) $c['raio_metros'], 'ativa' => (bool) $c['ativa'],
    ], $cercas),
    'editavel' => pode('gerenciar_disp'),
];

$titulo   = 'Cercas virtuais — Sentrix Guard Flow';
$usarMapa = true;
$scriptsPagina = ['cercas.js'];
require_once 'includes/header.php';
?>

<main>
  <section class="page-hero page-hero-compact">
    <div class="page-hero-orb"></div>
    <div class="page-hero-inner">
      <span class="section-label">// RASTREAMENTO</span>
      <h1 class="page-hero-title">CERCAS <span class="accent">VIRTUAIS</span></h1>
      <p class="page-hero-desc">Defina áreas no mapa. Quando um rastreador sai de uma cerca ativa, um alerta é gerado automaticamente.</p>
    </div>
  </section>

  <section class="page-section">
    <div class="page-section-inner">
      <nav class="tabs" aria-label="GPS">
        <a href="gps.php" class="tab">Mapa e histórico</a>
        <a href="cercas.php" class="tab active">Cercas virtuais</a>
      </nav>

      <?= flash_exibir() ?>

      <div class="panel-grid">
        <div class="panel-block">
          <div id="mapaCercas" class="gps-map"></div>
          <?php if (pode('gerenciar_disp')): ?>
            <p class="form-hint">Clique no mapa para escolher o centro da nova cerca.</p>
          <?php endif; ?>
        </div>

        <?php if (pode('gerenciar_disp')): ?>
        <div class="panel-block">
          <h2 class="block-title">Nova cerca</h2>
          <?php if ($erros): ?>
            <div class="msg msg-erro" role="alert"><ul><?php foreach ($erros as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
          <?php endif; ?>
          <form method="POST" action="cercas.php" class="form-stack" id="formCerca">
            <?= csrf_campo() ?>
            <input type="hidden" name="acao" value="criar">
            <label class="form-label" for="nome">Nome *</label>
            <input class="form-input" id="nome" name="nome" maxlength="120" required value="<?= e($form['nome']) ?>" placeholder="Ex.: Pátio da garagem">

            <label class="form-label" for="gps_dispositivo_id">Aplica-se a</label>
            <select class="form-input" id="gps_dispositivo_id" name="gps_dispositivo_id">
              <option value="">Todos os rastreadores</option>
              <?php foreach ($rastreadores as $r): ?>
                <option value="<?= (int) $r['id'] ?>" <?= (int) $form['gps_dispositivo_id'] === (int) $r['id'] ? 'selected' : '' ?>><?= e($r['nome']) ?></option>
              <?php endforeach; ?>
            </select>

            <div class="form-row">
              <div>
                <label class="form-label" for="centro_lat">Latitude *</label>
                <input class="form-input" id="centro_lat" name="centro_lat" inputmode="decimal" required value="<?= e($form['centro_lat']) ?>">
              </div>
              <div>
                <label class="form-label" for="centro_lng">Longitude *</label>
                <input class="form-input" id="centro_lng" name="centro_lng" inputmode="decimal" required value="<?= e($form['centro_lng']) ?>">
              </div>
            </div>

            <label class="form-label" for="raio_metros">Raio (metros) *</label>
            <input class="form-input" id="raio_metros" name="raio_metros" type="number" min="20" max="50000" step="10" required value="<?= (int) $form['raio_metros'] ?>">

            <button type="submit" class="btn-primary form-submit">Criar cerca</button>
          </form>
        </div>
        <?php endif; ?>
      </div>

      <div class="panel-block mt-28">
        <h2 class="block-title">Cercas cadastradas</h2>
        <?php if (!$cercas): ?>
          <p class="panel-empty">Nenhuma cerca virtual cadastrada.</p>
        <?php else: ?>
        <div class="panel-table-wrap">
          <table class="panel-table">
            <thead><tr><th>Nome</th><th>Rastreador</th><th>Centro</th><th>Raio</th><th>Situação</th><?php if (pode('gerenciar_disp')): ?><th class="col-acoes">Ações</th><?php endif; ?></tr></thead>
            <tbody>
              <?php foreach ($cercas as $c): ?>
              <tr>
                <td><strong class="td-destaque"><?= e($c['nome']) ?></strong></td>
                <td><?= e($c['rastreador'] ?? 'Todos') ?></td>
                <td class="td-mono"><?= number_format((float) $c['centro_lat'], 5) ?>, <?= number_format((float) $c['centro_lng'], 5) ?></td>
                <td><?= (int) $c['raio_metros'] ?> m</td>
                <td><span class="status-tag status-<?= $c['ativa'] ? 'online' : 'offline' ?>"><?= $c['ativa'] ? 'Ativa' : 'Inativa' ?></span></td>
                <?php if (pode('gerenciar_disp')): ?>
                <td class="col-acoes">
                  <form method="POST" action="cercas.php" class="inline-form">
                    <?= csrf_campo() ?>
                    <input type="hidden" name="acao" value="alternar">
                    <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                    <button type="submit" class="btn-outline btn-xs"><?= $c['ativa'] ? 'Desativar' : 'Ativar' ?></button>
                  </form>
                  <?php if (pode('excluir_disp')): ?>
                  <form method="POST" action="cercas.php" class="inline-form" data-confirmar="Excluir a cerca &quot;<?= e($c['nome']) ?>&quot;?">
                    <?= csrf_campo() ?>
                    <input type="hidden" name="acao" value="excluir">
                    <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                    <button type="submit" class="btn-danger btn-xs">Excluir</button>
                  </form>
                  <?php endif; ?>
                </td>
                <?php endif; ?>
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

<script type="application/json" id="dadosMapa"><?= json_encode($dadosMapa, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<?php require_once 'includes/footer.php'; ?>
