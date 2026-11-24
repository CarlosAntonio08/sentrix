<?php
/**
 * Gera eventos simulados pelo terminal (útil para deixar o sistema "vivo"
 * durante a apresentação).
 *
 * Uso:
 *   php scripts/simular.php            → gera uma rodada de eventos
 *   php scripts/simular.php 20 5       → 20 rodadas, uma a cada 5 segundos
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Este script só pode ser executado pelo terminal.');
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/simulador.php';

$rodadas   = max(1, (int) ($argv[1] ?? 1));
$intervalo = max(1, (int) ($argv[2] ?? 5));

for ($i = 1; $i <= $rodadas; $i++) {
    $r = simular_eventos($pdo);
    printf("[%s] rodada %d/%d: %d posições, %d alertas, %d saídas de cerca\n",
        date('H:i:s'), $i, $rodadas, $r['posicoes'], $r['alertas'], $r['saidas_cerca']);
    if ($i < $rodadas) sleep($intervalo);
}
