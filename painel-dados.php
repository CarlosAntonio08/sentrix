<?php
/**
 * Endpoint JSON com os indicadores do painel (atualização automática).
 */
require_once 'includes/auth.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

echo json_encode(['ok' => true, 'atualizado_em' => date('H:i:s')] + indicadores_painel($pdo));
