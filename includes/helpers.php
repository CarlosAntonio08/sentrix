<?php
/**
 * Funções auxiliares usadas em todo o sistema.
 */

/* ---------------------------------------------------------
 * Saída segura e navegação
 * ------------------------------------------------------- */

/** Escapa texto para exibir em HTML (proteção contra XSS). */
function e($valor): string
{
    return htmlspecialchars((string) ($valor ?? ''), ENT_QUOTES, 'UTF-8');
}

/** Redireciona e encerra o script. */
function redirecionar(string $url): void
{
    header('Location: ' . $url);
    exit;
}

/** IP de quem fez a requisição (para auditoria). */
function ip_cliente(): string
{
    return substr($_SERVER['REMOTE_ADDR'] ?? '', 0, 45);
}

/* ---------------------------------------------------------
 * CSRF — impede que outro site dispare ações em nome do usuário
 * ------------------------------------------------------- */

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Campo oculto para colocar dentro de todo <form method="POST">. */
function csrf_campo(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/** Confere o token enviado; se for inválido, interrompe a requisição. */
function csrf_verificar(): void
{
    $enviado = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!is_string($enviado) || !hash_equals(csrf_token(), $enviado)) {
        http_response_code(419);
        if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')) {
            header('Content-Type: application/json; charset=utf-8');
            exit(json_encode(['ok' => false, 'erro' => 'Sessão expirada. Recarregue a página.']));
        }
        exit('Requisição inválida ou sessão expirada. Volte, recarregue a página e tente novamente.');
    }
}

/* ---------------------------------------------------------
 * Mensagens "flash" (aparecem uma vez após um redirecionamento)
 * ------------------------------------------------------- */

function flash(string $tipo, string $mensagem): void
{
    $_SESSION['flash'][] = ['tipo' => $tipo, 'mensagem' => $mensagem];
}

/** Exibe e limpa as mensagens pendentes. Tipos: ok, erro, info. */
function flash_exibir(): string
{
    $html = '';
    foreach ($_SESSION['flash'] ?? [] as $f) {
        $icone = $f['tipo'] === 'ok' ? '✓' : ($f['tipo'] === 'erro' ? '⚠' : 'ℹ');
        $html .= '<p class="msg msg-' . e($f['tipo']) . '" role="status">' . $icone . ' ' . e($f['mensagem']) . '</p>';
    }
    unset($_SESSION['flash']);
    return $html ? '<div class="flash-area">' . $html . '</div>' : '';
}

/* ---------------------------------------------------------
 * Usuário logado e permissões por cargo
 * ------------------------------------------------------- */

/**
 * Matriz de permissões do sistema.
 *  - admin:        tudo (inclusive usuários e logs)
 *  - operador:     opera o sistema (alertas, câmeras, dispositivos, cercas, simulador)
 *  - visualizador: apenas consulta
 */
const PERMISSOES = [
    'ver'                => ['admin', 'operador', 'visualizador'],
    'operar'             => ['admin', 'operador'],               // resolver alertas, gravação, simulador
    'gerenciar_disp'     => ['admin', 'operador'],               // criar/editar dispositivos e cercas
    'excluir_disp'       => ['admin'],                           // excluir dispositivos e cercas
    'gerenciar_usuarios' => ['admin'],
    'ver_logs'           => ['admin'],
];

function usuario_logado(): ?array
{
    return $GLOBALS['usuarioLogado'] ?? null;
}

function pode(string $permissao): bool
{
    $u = usuario_logado();
    return $u !== null && in_array($u['cargo'], PERMISSOES[$permissao] ?? [], true);
}

/** Bloqueia a página/ação se o usuário não tiver a permissão. */
function exigir_permissao(string $permissao): void
{
    if (!pode($permissao)) {
        http_response_code(403);
        flash('erro', 'Você não tem permissão para esta ação. Fale com um administrador.');
        redirecionar('painel.php');
    }
}

/* ---------------------------------------------------------
 * Auditoria
 * ------------------------------------------------------- */

function registrar_log(PDO $pdo, ?int $usuarioId, string $tipo, string $descricao = ''): void
{
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO logs_eventos (usuario_id, tipo_evento, descricao, ip_origem) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$usuarioId, $tipo, mb_substr($descricao, 0, 255), ip_cliente()]);
    } catch (Throwable $e) {
        error_log('[Sentrix] Falha ao registrar log: ' . $e->getMessage());
    }
}

/* ---------------------------------------------------------
 * Rótulos usados nas telas
 * ------------------------------------------------------- */

const ROTULOS = [
    'nivel'  => ['baixo' => 'Baixo', 'medio' => 'Médio', 'critico' => 'Crítico'],
    'status_alerta' => ['novo' => 'Novo', 'visualizado' => 'Visualizado', 'resolvido' => 'Resolvido'],
    'tipo_disp' => ['camera' => 'Câmera', 'sensor' => 'Sensor', 'gps' => 'GPS', 'alarme' => 'Alarme', 'outro' => 'Outro'],
    'status_disp' => ['online' => 'Online', 'offline' => 'Offline', 'manutencao' => 'Manutenção'],
    'tipo_alerta' => [
        'movimento' => 'Movimento', 'intrusao' => 'Intrusão', 'falha_equipamento' => 'Falha de equipamento',
        'acesso_nao_autorizado' => 'Acesso não autorizado', 'queda_conexao' => 'Queda de conexão',
        'deteccao_ia' => 'Detecção por IA', 'saida_cerca' => 'Saída de cerca virtual', 'outro' => 'Outro',
    ],
    'deteccao' => [
        'rosto_desconhecido' => 'Rosto desconhecido', 'veiculo' => 'Veículo', 'pessoa' => 'Pessoa',
        'animal' => 'Animal', 'objeto_abandonado' => 'Objeto abandonado',
        'comportamento_suspeito' => 'Comportamento suspeito', 'aglomeracao' => 'Aglomeração',
    ],
    'tipo_gps' => ['veiculo' => 'Veículo', 'pessoa' => 'Pessoa', 'ativo' => 'Ativo'],
    'cargo'    => ['admin' => 'Administrador', 'operador' => 'Operador', 'visualizador' => 'Visualizador'],
];

function rotulo(string $grupo, ?string $chave): string
{
    return ROTULOS[$grupo][$chave] ?? (string) $chave;
}

/* ---------------------------------------------------------
 * Validação e cálculo
 * ------------------------------------------------------- */

/** Regra de senha: mínimo 8 caracteres, com pelo menos uma letra e um número. */
function senha_valida(string $senha): bool
{
    return mb_strlen($senha) >= 8 && preg_match('/[A-Za-z]/', $senha) && preg_match('/\d/', $senha);
}

/** Distância em metros entre duas coordenadas (fórmula de Haversine). */
function distancia_metros(float $lat1, float $lng1, float $lat2, float $lng2): float
{
    $raioTerra = 6371000;
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
    return $raioTerra * 2 * atan2(sqrt($a), sqrt(1 - $a));
}

function coordenada_valida($lat, $lng): bool
{
    return is_numeric($lat) && is_numeric($lng)
        && $lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180;
}

/* ---------------------------------------------------------
 * Consultas compartilhadas
 * ------------------------------------------------------- */

/** Indicadores do painel (usados na página e na atualização automática). */
function indicadores_painel(PDO $pdo): array
{
    $l = $pdo->query(
        "SELECT
            (SELECT COUNT(*) FROM dispositivos)                                              AS total_dispositivos,
            (SELECT COUNT(*) FROM dispositivos WHERE status = 'online')                      AS online,
            (SELECT COUNT(*) FROM alertas WHERE status = 'novo')                             AS alertas_novos,
            (SELECT COUNT(*) FROM alertas WHERE nivel = 'critico' AND status <> 'resolvido') AS criticos,
            (SELECT COALESCE(MAX(id), 0) FROM alertas)                                       AS ultimo_alerta_id"
    )->fetch();
    return array_map('intval', $l);
}
