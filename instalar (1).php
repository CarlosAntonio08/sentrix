<?php
/**
 * Instala (ou restaura) o banco de dados pelo terminal.
 *
 *   php scripts/instalar.php            → cria o banco só se ainda não existir
 *   php scripts/instalar.php --restaurar → apaga tudo e volta aos dados de demonstração
 *
 * Usado automaticamente pelo INICIAR.bat.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Este script só pode ser executado pelo terminal.');
}
require_once __DIR__ . '/../includes/instalador.php';

$restaurar = in_array('--restaurar', $argv, true);

foreach (verificar_requisitos() as $p) {
    fwrite(STDERR, "[ERRO] {$p}\n");
    exit(1);
}

// O MySQL pode levar alguns segundos para subir: tenta por até ~30 s
$pdo = null;
for ($i = 0; $i < 15 && !$pdo; $i++) {
    try {
        $pdo = conectar_servidor(DB_HOST, (string) DB_PORT, DB_USER, DB_PASS);
    } catch (PDOException $e) {
        if (in_array((int) $e->getCode(), [1045, 1698], true)) {
            fwrite(STDERR, '[ERRO] ' . explicar_erro_mysql($e) . "\n");
            exit(1);
        }
        if ($i === 0) echo "Aguardando o MySQL iniciar";
        echo '.';
        sleep(2);
    }
}
if (!$pdo) {
    fwrite(STDERR, "\n[ERRO] Não foi possível conectar ao MySQL em " . DB_HOST . ':' . DB_PORT . ".\n");
    exit(1);
}
if ($i > 1) echo "\n";

if (!$restaurar && banco_instalado($pdo)) {
    marcar_instalado();
    echo "Banco de dados já instalado. OK\n";
    exit(0);
}

$n = executar_schema($pdo);
marcar_instalado();
echo ($restaurar ? 'Dados de demonstração restaurados' : 'Banco de dados criado') . " ({$n} comandos). OK\n";
