<?php
/**
 * Ação do botão "Gerar eventos simulados" (painel). Apenas admin/operador.
 */
require_once 'includes/auth.php';
require_once 'includes/simulador.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirecionar('painel.php');
}
csrf_verificar();
exigir_permissao('operar');

try {
    $r = simular_eventos($pdo, true);
    registrar_log($pdo, $usuarioLogado['id'], 'simulacao',
        "Simulação: {$r['posicoes']} posições GPS, {$r['alertas']} alertas");
    flash('ok', "Simulação concluída: {$r['posicoes']} novas posições GPS e {$r['alertas']} novo(s) alerta(s)"
        . ($r['saidas_cerca'] ? " — {$r['saidas_cerca']} saída(s) de cerca virtual." : '.'));
} catch (Throwable $e) {
    error_log('[Sentrix] Erro no simulador: ' . $e->getMessage());
    flash('erro', 'Não foi possível gerar os eventos simulados.');
}

$voltar = $_POST['voltar'] ?? 'painel.php';
redirecionar(in_array($voltar, ['painel.php', 'gps.php', 'alertas-ia.php'], true) ? $voltar : 'painel.php');
