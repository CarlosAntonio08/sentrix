<?php
/**
 * Gestão de dispositivos (CRUD): câmeras, sensores, rastreadores GPS e alarmes.
 *  - Todos os usuários logados podem consultar.
 *  - Admin e operador podem cadastrar e editar.
 *  - Somente admin pode excluir.
 */
require_once 'includes/auth.php';

$tipos        = array_keys(ROTULOS['tipo_disp']);
$statusLista  = array_keys(ROTULOS['status_disp']);
$tiposCamera  = ['fixa' => 'Fixa', 'ptz' => 'PTZ (móvel)'];
$tiposGps     = array_keys(ROTULOS['tipo_gps']);

$erros = [];
$form  = null; // dados do formulário quando estiver criando/editando

/* =========================================================
 * AÇÕES (POST)
 * ======================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verificar();
    $acao = $_POST['acao'] ?? '';

    /* ----- Excluir ----- */
    if ($acao === 'excluir') {
        exigir_permissao('excluir_disp');
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('SELECT nome FROM dispositivos WHERE id = ?');
        $stmt->execute([$id]);
        if ($nome = $stmt->fetchColumn()) {
            $pdo->prepare('DELETE FROM dispositivos WHERE id = ?')->execute([$id]);
            registrar_log($pdo, $usuarioLogado['id'], 'dispositivo_excluido', "Dispositivo #{$id} ({$nome}) excluído");
            flash('ok', "Dispositivo \"{$nome}\" excluído.");
        }
        redirecionar('dispositivos.php');
    }

    /* ----- Criar / editar ----- */
    if ($acao === 'salvar') {
        exigir_permissao('gerenciar_disp');

        $form = [
            'id'          => (int) ($_POST['id'] ?? 0),
            'nome'        => trim($_POST['nome'] ?? ''),
            'tipo'        => $_POST['tipo'] ?? '',
            'status'      => $_POST['status'] ?? 'offline',
            'localizacao' => trim($_POST['localizacao'] ?? ''),
            'modelo'      => trim($_POST['modelo'] ?? ''),
            'tipo_camera' => $_POST['tipo_camera'] ?? 'fixa',
            'resolucao'   => trim($_POST['resolucao'] ?? ''),
            'ip_address'  => trim($_POST['ip_address'] ?? ''),
            'rtsp_url'    => trim($_POST['rtsp_url'] ?? ''),
            'tipo_gps'    => $_POST['tipo_gps'] ?? 'veiculo',
            'gps_ativo'   => !empty($_POST['gps_ativo']),
            'latitude'    => trim($_POST['latitude'] ?? ''),
            'longitude'   => trim($_POST['longitude'] ?? ''),
        ];

        // Validações
        if ($form['nome'] === '' || mb_strlen($form['nome']) > 120) $erros[] = 'Informe um nome (até 120 caracteres).';
        if (!in_array($form['tipo'], $tipos, true))                  $erros[] = 'Selecione um tipo válido.';
        if (!in_array($form['status'], $statusLista, true))          $erros[] = 'Selecione um status válido.';
        if (mb_strlen($form['localizacao']) > 160)                   $erros[] = 'Localização muito longa (máx. 160).';

        if ($form['tipo'] === 'camera') {
            if (!array_key_exists($form['tipo_camera'], $tiposCamera)) $erros[] = 'Tipo de câmera inválido.';
            if ($form['ip_address'] !== '' && !filter_var($form['ip_address'], FILTER_VALIDATE_IP)) $erros[] = 'Endereço IP inválido.';
            if ($form['rtsp_url'] !== '' && !preg_match('#^rtsps?://#i', $form['rtsp_url'])) $erros[] = 'A URL do stream deve começar com rtsp://';
            if (mb_strlen($form['rtsp_url']) > 255 || mb_strlen($form['modelo']) > 120 || mb_strlen($form['resolucao']) > 20) $erros[] = 'Algum campo da câmera está longo demais.';
        }
        if ($form['tipo'] === 'gps') {
            if (!in_array($form['tipo_gps'], $tiposGps, true)) $erros[] = 'Tipo de rastreamento inválido.';
            $temLat = $form['latitude'] !== '' || $form['longitude'] !== '';
            if ($temLat && !coordenada_valida($form['latitude'], $form['longitude'])) {
                $erros[] = 'Latitude deve estar entre -90 e 90 e longitude entre -180 e 180.';
            }
        }

        if ($form['id']) {
            $stmt = $pdo->prepare('SELECT id FROM dispositivos WHERE id = ?');
            $stmt->execute([$form['id']]);
            if (!$stmt->fetch()) $erros[] = 'Dispositivo não encontrado.';
        }

        if (!$erros) {
            $pdo->beginTransaction();
            try {
                if ($form['id']) {
                    $pdo->prepare('UPDATE dispositivos SET nome = ?, tipo = ?, status = ?, localizacao = ? WHERE id = ?')
                        ->execute([$form['nome'], $form['tipo'], $form['status'], $form['localizacao'] ?: null, $form['id']]);
                    $id = $form['id'];
                } else {
                    $pdo->prepare('INSERT INTO dispositivos (nome, tipo, status, localizacao, usuario_id) VALUES (?, ?, ?, ?, ?)')
                        ->execute([$form['nome'], $form['tipo'], $form['status'], $form['localizacao'] ?: null, $usuarioLogado['id']]);
                    $id = (int) $pdo->lastInsertId();
                }

                // Detalhes de câmera
                if ($form['tipo'] === 'camera') {
                    $cam = $pdo->prepare('SELECT id FROM cameras WHERE dispositivo_id = ?');
                    $cam->execute([$id]);
                    $dados = [$form['modelo'] ?: null, $form['tipo_camera'], $form['resolucao'] ?: 'Full HD',
                              $form['ip_address'] ?: null, $form['rtsp_url'] ?: null];
                    if ($camId = $cam->fetchColumn()) {
                        $pdo->prepare('UPDATE cameras SET modelo = ?, tipo = ?, resolucao = ?, ip_address = ?, rtsp_url = ? WHERE id = ?')
                            ->execute([...$dados, $camId]);
                    } else {
                        $pdo->prepare('INSERT INTO cameras (modelo, tipo, resolucao, ip_address, rtsp_url, dispositivo_id) VALUES (?, ?, ?, ?, ?, ?)')
                            ->execute([...$dados, $id]);
                    }
                } else {
                    $pdo->prepare('DELETE FROM cameras WHERE dispositivo_id = ?')->execute([$id]);
                }

                // Detalhes de rastreador GPS
                if ($form['tipo'] === 'gps') {
                    $gps = $pdo->prepare('SELECT id FROM gps_dispositivos WHERE dispositivo_id = ?');
                    $gps->execute([$id]);
                    if ($gpsId = $gps->fetchColumn()) {
                        $pdo->prepare('UPDATE gps_dispositivos SET nome = ?, tipo = ?, ativo = ? WHERE id = ?')
                            ->execute([$form['nome'], $form['tipo_gps'], (int) $form['gps_ativo'], $gpsId]);
                    } else {
                        $pdo->prepare('INSERT INTO gps_dispositivos (dispositivo_id, nome, tipo, ativo) VALUES (?, ?, ?, ?)')
                            ->execute([$id, $form['nome'], $form['tipo_gps'], (int) $form['gps_ativo']]);
                        $gpsId = (int) $pdo->lastInsertId();
                    }
                    if ($form['latitude'] !== '' && $form['longitude'] !== '') {
                        $pdo->prepare('INSERT INTO gps_localizacoes (gps_dispositivo_id, latitude, longitude, velocidade_kmh) VALUES (?, ?, ?, 0)')
                            ->execute([$gpsId, $form['latitude'], $form['longitude']]);
                    }
                } else {
                    $pdo->prepare('DELETE FROM gps_dispositivos WHERE dispositivo_id = ?')->execute([$id]);
                }

                $pdo->commit();
            } catch (Throwable $ex) {
                $pdo->rollBack();
                error_log('[Sentrix] Erro ao salvar dispositivo: ' . $ex->getMessage());
                $erros[] = 'Não foi possível salvar. Tente novamente.';
            }

            if (!$erros) {
                $verbo = $form['id'] ? 'editado' : 'cadastrado';
                registrar_log($pdo, $usuarioLogado['id'], 'dispositivo_' . ($form['id'] ? 'editado' : 'criado'),
                    "Dispositivo #{$id} ({$form['nome']}) {$verbo}");
                flash('ok', "Dispositivo \"{$form['nome']}\" {$verbo} com sucesso.");
                redirecionar('dispositivos.php');
            }
        }
    }
}

/* =========================================================
 * FORMULÁRIO (GET ?novo=1 ou ?editar=ID)
 * ======================================================= */
if (!$form && (isset($_GET['novo']) || isset($_GET['editar']))) {
    exigir_permissao('gerenciar_disp');
    $form = ['id' => 0, 'nome' => '', 'tipo' => 'camera', 'status' => 'online', 'localizacao' => '',
             'modelo' => '', 'tipo_camera' => 'fixa', 'resolucao' => 'Full HD', 'ip_address' => '', 'rtsp_url' => '',
             'tipo_gps' => 'veiculo', 'gps_ativo' => true, 'latitude' => '', 'longitude' => ''];

    if (isset($_GET['editar'])) {
        $stmt = $pdo->prepare(
            "SELECT d.id, d.nome, d.tipo, d.status, d.localizacao,
                    c.modelo, c.tipo AS tipo_camera, c.resolucao, c.ip_address, c.rtsp_url,
                    g.tipo AS tipo_gps, g.ativo AS gps_ativo
             FROM dispositivos d
             LEFT JOIN cameras c ON c.dispositivo_id = d.id
             LEFT JOIN gps_dispositivos g ON g.dispositivo_id = d.id
             WHERE d.id = ?"
        );
        $stmt->execute([(int) $_GET['editar']]);
        $d = $stmt->fetch();
        if (!$d) {
            flash('erro', 'Dispositivo não encontrado.');
            redirecionar('dispositivos.php');
        }
        foreach ($d as $k => $v) {
            if ($v !== null) $form[$k] = $v;
        }
        $form['id'] = (int) $d['id'];
        $form['gps_ativo'] = $d['gps_ativo'] === null ? true : (bool) $d['gps_ativo'];
    }
}

/* =========================================================
 * LISTAGEM
 * ======================================================= */
$filtroTipo = $_GET['tipo'] ?? '';
$busca      = trim($_GET['busca'] ?? '');
$where = [];
$params = [];
if (in_array($filtroTipo, $tipos, true)) { $where[] = 'd.tipo = ?'; $params[] = $filtroTipo; }
if ($busca !== '') { $where[] = '(d.nome LIKE ? OR d.localizacao LIKE ?)'; $params[] = "%{$busca}%"; $params[] = "%{$busca}%"; }

$stmt = $pdo->prepare(
    "SELECT d.id, d.nome, d.tipo, d.status, d.localizacao, d.criado_em,
            c.modelo, c.ip_address, g.tipo AS tipo_gps
     FROM dispositivos d
     LEFT JOIN cameras c ON c.dispositivo_id = d.id
     LEFT JOIN gps_dispositivos g ON g.dispositivo_id = d.id
     " . ($where ? 'WHERE ' . implode(' AND ', $where) : '') . "
     ORDER BY d.nome"
);
$stmt->execute($params);
$dispositivos = $stmt->fetchAll();

$titulo = 'Dispositivos — Sentrix Guard Flow';
require_once 'includes/header.php';
?>

<main>
  <section class="page-hero page-hero-compact">
    <div class="page-hero-orb"></div>
    <div class="page-hero-inner">
      <span class="section-label">// GESTÃO</span>
      <h1 class="page-hero-title">DISPOSITIVOS <span class="accent">CONECTADOS</span></h1>
      <p class="page-hero-desc">Cadastre e mantenha câmeras, sensores, rastreadores GPS e alarmes.</p>
    </div>
  </section>

  <section class="page-section">
    <div class="page-section-inner">
      <?= flash_exibir() ?>

      <?php if ($form): ?>
      <!-- ===================== FORMULÁRIO ===================== -->
      <div class="panel-block form-block">
        <div class="panel-block-header">
          <h2 class="block-title"><?= $form['id'] ? 'Editar dispositivo' : 'Novo dispositivo' ?></h2>
          <a href="dispositivos.php" class="panel-link">← Voltar à lista</a>
        </div>

        <?php if ($erros): ?>
          <div class="msg msg-erro" role="alert">
            <strong>Corrija os campos abaixo:</strong>
            <ul><?php foreach ($erros as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul>
          </div>
        <?php endif; ?>

        <form method="POST" action="dispositivos.php" class="form-grid" id="formDispositivo">
          <?= csrf_campo() ?>
          <input type="hidden" name="acao" value="salvar">
          <input type="hidden" name="id" value="<?= (int) $form['id'] ?>">

          <div class="form-field form-span-2">
            <label class="form-label" for="nome">Nome *</label>
            <input class="form-input" id="nome" name="nome" maxlength="120" required value="<?= e($form['nome']) ?>" placeholder="Ex.: Câmera Portão Lateral">
          </div>
          <div class="form-field">
            <label class="form-label" for="tipo">Tipo *</label>
            <select class="form-input" id="tipo" name="tipo" required>
              <?php foreach ($tipos as $t): ?>
                <option value="<?= $t ?>" <?= $form['tipo'] === $t ? 'selected' : '' ?>><?= e(rotulo('tipo_disp', $t)) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-field">
            <label class="form-label" for="status">Status *</label>
            <select class="form-input" id="status" name="status">
              <?php foreach ($statusLista as $s): ?>
                <option value="<?= $s ?>" <?= $form['status'] === $s ? 'selected' : '' ?>><?= e(rotulo('status_disp', $s)) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-field form-span-2">
            <label class="form-label" for="localizacao">Localização</label>
            <input class="form-input" id="localizacao" name="localizacao" maxlength="160" value="<?= e($form['localizacao']) ?>" placeholder="Ex.: Portão Lateral">
          </div>

          <fieldset class="form-fieldset form-span-2" data-tipo="camera">
            <legend>Dados da câmera</legend>
            <div class="form-grid">
              <div class="form-field">
                <label class="form-label" for="modelo">Modelo</label>
                <input class="form-input" id="modelo" name="modelo" maxlength="120" value="<?= e($form['modelo']) ?>">
              </div>
              <div class="form-field">
                <label class="form-label" for="tipo_camera">Tipo de câmera</label>
                <select class="form-input" id="tipo_camera" name="tipo_camera">
                  <?php foreach ($tiposCamera as $v => $r): ?>
                    <option value="<?= $v ?>" <?= $form['tipo_camera'] === $v ? 'selected' : '' ?>><?= $r ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-field">
                <label class="form-label" for="resolucao">Resolução</label>
                <input class="form-input" id="resolucao" name="resolucao" maxlength="20" value="<?= e($form['resolucao']) ?>" placeholder="Full HD, 4K…">
              </div>
              <div class="form-field">
                <label class="form-label" for="ip_address">Endereço IP</label>
                <input class="form-input" id="ip_address" name="ip_address" maxlength="45" value="<?= e($form['ip_address']) ?>" placeholder="192.168.0.110">
              </div>
              <div class="form-field form-span-2">
                <label class="form-label" for="rtsp_url">URL do stream (RTSP)</label>
                <input class="form-input" id="rtsp_url" name="rtsp_url" maxlength="255" value="<?= e($form['rtsp_url']) ?>" placeholder="rtsp://192.168.0.110/stream1">
              </div>
            </div>
          </fieldset>

          <fieldset class="form-fieldset form-span-2" data-tipo="gps">
            <legend>Dados do rastreador GPS</legend>
            <div class="form-grid">
              <div class="form-field">
                <label class="form-label" for="tipo_gps">O que é rastreado</label>
                <select class="form-input" id="tipo_gps" name="tipo_gps">
                  <?php foreach ($tiposGps as $t): ?>
                    <option value="<?= $t ?>" <?= $form['tipo_gps'] === $t ? 'selected' : '' ?>><?= e(rotulo('tipo_gps', $t)) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-field form-field-check">
                <label class="form-check">
                  <input type="checkbox" name="gps_ativo" value="1" <?= $form['gps_ativo'] ? 'checked' : '' ?>>
                  <span>Rastreamento ativo</span>
                </label>
              </div>
              <div class="form-field">
                <label class="form-label" for="latitude">Latitude</label>
                <input class="form-input" id="latitude" name="latitude" inputmode="decimal" value="<?= e($form['latitude']) ?>" placeholder="-22.9068">
              </div>
              <div class="form-field">
                <label class="form-label" for="longitude">Longitude</label>
                <input class="form-input" id="longitude" name="longitude" inputmode="decimal" value="<?= e($form['longitude']) ?>" placeholder="-43.1729">
              </div>
              <p class="form-hint form-span-2">Opcional: ao preencher, uma nova posição é registrada no histórico do rastreador.</p>
            </div>
          </fieldset>

          <div class="form-actions form-span-2">
            <a href="dispositivos.php" class="btn-outline btn-sm">Cancelar</a>
            <button type="submit" class="btn-primary"><?= $form['id'] ? 'Salvar alterações' : 'Cadastrar dispositivo' ?></button>
          </div>
        </form>
      </div>

      <?php else: ?>
      <!-- ===================== LISTA ===================== -->
      <div class="toolbar">
        <form method="GET" action="dispositivos.php" class="toolbar-filtros">
          <input class="form-input form-input-sm" type="search" name="busca" value="<?= e($busca) ?>" placeholder="Buscar por nome ou local" aria-label="Buscar">
          <select class="form-input form-input-sm" name="tipo" aria-label="Filtrar por tipo">
            <option value="">Todos os tipos</option>
            <?php foreach ($tipos as $t): ?>
              <option value="<?= $t ?>" <?= $filtroTipo === $t ? 'selected' : '' ?>><?= e(rotulo('tipo_disp', $t)) ?></option>
            <?php endforeach; ?>
          </select>
          <button class="btn-outline btn-sm" type="submit">Filtrar</button>
        </form>
        <?php if (pode('gerenciar_disp')): ?>
          <a href="dispositivos.php?novo=1" class="btn-primary">+ Novo dispositivo</a>
        <?php endif; ?>
      </div>

      <?php if (!pode('gerenciar_disp')): ?>
        <p class="msg msg-info">ℹ Seu perfil é de visualização. Para cadastrar ou editar dispositivos, peça acesso de operador a um administrador.</p>
      <?php endif; ?>

      <div class="panel-block">
        <?php if (!$dispositivos): ?>
          <p class="panel-empty">Nenhum dispositivo encontrado.</p>
        <?php else: ?>
        <div class="panel-table-wrap">
          <table class="panel-table">
            <thead>
              <tr><th>Nome</th><th>Tipo</th><th>Status</th><th>Localização</th><th>Detalhes</th><?php if (pode('gerenciar_disp')): ?><th class="col-acoes">Ações</th><?php endif; ?></tr>
            </thead>
            <tbody>
              <?php foreach ($dispositivos as $d): ?>
              <tr>
                <td><strong class="td-destaque"><?= e($d['nome']) ?></strong></td>
                <td><?= e(rotulo('tipo_disp', $d['tipo'])) ?></td>
                <td><span class="status-tag status-<?= e($d['status']) ?>"><span class="device-dot device-dot-<?= e($d['status']) ?>"></span><?= e(rotulo('status_disp', $d['status'])) ?></span></td>
                <td><?= e($d['localizacao'] ?: '—') ?></td>
                <td class="td-mono">
                  <?php if ($d['tipo'] === 'camera'): ?><?= e($d['modelo'] ?: '—') ?><?= $d['ip_address'] ? ' · ' . e($d['ip_address']) : '' ?>
                  <?php elseif ($d['tipo'] === 'gps'): ?><?= e(rotulo('tipo_gps', $d['tipo_gps'])) ?>
                  <?php else: ?>—<?php endif; ?>
                </td>
                <?php if (pode('gerenciar_disp')): ?>
                <td class="col-acoes">
                  <a href="dispositivos.php?editar=<?= (int) $d['id'] ?>" class="btn-outline btn-xs">Editar</a>
                  <?php if (pode('excluir_disp')): ?>
                  <form method="POST" action="dispositivos.php" class="inline-form" data-confirmar="Excluir o dispositivo &quot;<?= e($d['nome']) ?>&quot;? Os alertas ficam no histórico sem vínculo.">
                    <?= csrf_campo() ?>
                    <input type="hidden" name="acao" value="excluir">
                    <input type="hidden" name="id" value="<?= (int) $d['id'] ?>">
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
      <?php endif; ?>
    </div>
  </section>
</main>

<?php require_once 'includes/footer.php'; ?>
