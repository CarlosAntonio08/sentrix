<?php
/**
 * Exporta os alertas em CSV (abre direto no Excel / LibreOffice / Google Planilhas).
 * Respeita o mesmo filtro de status da tela de alertas.
 */
require_once __DIR__ . '/includes/auth.php';

$filtro = $_GET['status'] ?? 'todos';
$where  = '';
$params = [];
if (array_key_exists($filtro, ROTULOS['status_alerta'])) {
    $where = 'WHERE a.status = ?';
    $params[] = $filtro;
}

$stmt = $pdo->prepare(
    "SELECT a.id, a.criado_em, a.nivel, a.tipo, a.status, a.descricao, d.nome AS dispositivo,
            di.tipo_deteccao, di.confianca
     FROM alertas a
     LEFT JOIN dispositivos d ON d.id = a.dispositivo_id
     LEFT JOIN deteccoes_ia di ON di.alerta_id = a.id
     {$where}
     ORDER BY a.criado_em DESC, a.id DESC"
);
$stmt->execute($params);

registrar_log($pdo, $usuarioLogado['id'], 'alertas_exportados', "Exportação CSV de alertas (filtro: {$filtro})");

$nomeArquivo = 'alertas-sentrix-' . date('Y-m-d-His') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $nomeArquivo . '"');

$saida = fopen('php://output', 'w');
fwrite($saida, "\xEF\xBB\xBF"); // BOM: faz o Excel reconhecer os acentos

/** Evita "injeção de fórmula" quando o CSV é aberto em planilhas. */
$limpar = fn ($v) => is_string($v) && preg_match('/^[=+\-@\t\r]/', $v) ? "'" . $v : $v;

fputcsv($saida, ['ID', 'Data/hora', 'Nível', 'Tipo', 'Status', 'Dispositivo', 'Descrição', 'Detecção IA', 'Confiança (%)'], ';');
while ($a = $stmt->fetch()) {
    fputcsv($saida, array_map($limpar, [
        $a['id'],
        date('d/m/Y H:i:s', strtotime($a['criado_em'])),
        rotulo('nivel', $a['nivel']),
        rotulo('tipo_alerta', $a['tipo']),
        rotulo('status_alerta', $a['status']),
        $a['dispositivo'] ?? '',
        $a['descricao'] ?? '',
        $a['tipo_deteccao'] ? rotulo('deteccao', $a['tipo_deteccao']) : '',
        $a['confianca'] !== null ? number_format((float) $a['confianca'], 2, ',', '') : '',
    ]), ';');
}
fclose($saida);
