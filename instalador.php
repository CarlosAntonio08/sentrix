<?php
/**
 * Funções do instalador automático (usadas por instalar.php e scripts/instalar.php).
 * Criam o banco a partir do schema.sql, sem precisar do phpMyAdmin.
 */
require_once __DIR__ . '/../config/config.php';

const ARQUIVO_SCHEMA = __DIR__ . '/../schema.sql';

/** Verifica se o PHP tem o que o sistema precisa. Retorna lista de problemas. */
function verificar_requisitos(): array
{
    $problemas = [];
    if (PHP_VERSION_ID < 80000) $problemas[] = 'PHP 8.0 ou superior é necessário (versão atual: ' . PHP_VERSION . ').';
    if (!extension_loaded('pdo_mysql')) $problemas[] = 'A extensão pdo_mysql do PHP não está habilitada (php.ini: extension=pdo_mysql).';
    if (!extension_loaded('mbstring'))  $problemas[] = 'A extensão mbstring do PHP não está habilitada (php.ini: extension=mbstring).';
    if (!is_file(ARQUIVO_SCHEMA))       $problemas[] = 'O arquivo schema.sql não foi encontrado na pasta do projeto.';
    if (!is_writable(dirname(ARQUIVO_INSTALADO))) $problemas[] = 'A pasta config/ precisa permitir gravação.';
    return $problemas;
}

/** Conecta ao servidor MySQL (sem escolher banco). */
function conectar_servidor(string $host, string $porta, string $usuario, string $senha): PDO
{
    return new PDO("mysql:host={$host};port={$porta};charset=utf8mb4", $usuario, $senha, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 5,
    ]);
}

/** O banco já existe e tem as tabelas do sistema? */
function banco_instalado(PDO $pdo): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ?');
    $stmt->execute([DB_NAME]);
    return (int) $stmt->fetchColumn() >= 10;
}

/** Executa o schema.sql comando por comando. Retorna quantos comandos rodou. */
function executar_schema(PDO $pdo): int
{
    $sql = file_get_contents(ARQUIVO_SCHEMA);
    $sql = preg_replace('/^\xEF\xBB\xBF/', '', $sql);          // remove BOM, se houver
    $sql = preg_replace('/^\s*--.*$/m', '', $sql);               // linhas de comentário
    $sql = preg_replace('/;[ \t]+--[^\n]*$/m', ';', $sql);       // comentário depois do ;
    $sql = preg_replace('/[ \t]+--\s[^\n\']*$/m', '', $sql);     // comentário no fim da linha

    $comandos = array_filter(array_map('trim', preg_split('/;\s*(\r?\n|$)/', $sql)));
    foreach ($comandos as $comando) {
        $pdo->exec($comando);
    }
    return count($comandos);
}

/** Grava config/config.local.php só se as credenciais diferirem do padrão. */
function gravar_config_local(string $host, string $porta, string $usuario, string $senha): void
{
    $arquivo = __DIR__ . '/../config/config.local.php';
    if ($host === '127.0.0.1' && $porta === '3306' && $usuario === 'root' && $senha === '') {
        if (is_file($arquivo)) @unlink($arquivo);
        return;
    }
    $conteudo = "<?php\n// Gerado pelo instalador em " . date('d/m/Y H:i') . ". Pode editar ou apagar.\n"
        . "define('DB_HOST', " . var_export($host, true) . ");\n"
        . "define('DB_PORT', " . var_export($porta, true) . ");\n"
        . "define('DB_USER', " . var_export($usuario, true) . ");\n"
        . "define('DB_PASS', " . var_export($senha, true) . ");\n";
    file_put_contents($arquivo, $conteudo);
    if (function_exists('opcache_invalidate')) opcache_invalidate($arquivo, true);
}

function marcar_instalado(): void
{
    file_put_contents(ARQUIVO_INSTALADO, 'Instalado em ' . date('c') . PHP_EOL);
    @unlink(ARQUIVO_MIGRACAO); // garante que as tabelas novas sejam conferidas no próximo acesso
}

/** Mensagem amigável para os erros mais comuns de conexão. */
function explicar_erro_mysql(PDOException $e): string
{
    $codigo = (int) $e->getCode();
    $msg = $e->getMessage();
    if ($codigo === 2002 || str_contains($msg, 'refused') || str_contains($msg, 'No connection')) {
        return 'O MySQL não está respondendo. Abra o XAMPP e clique em "Start" ao lado de MySQL (ou use o INICIAR.bat).';
    }
    if ($codigo === 1045 || $codigo === 1698) {
        return 'Usuário ou senha do MySQL recusados. No XAMPP o padrão é usuário "root" e senha em branco.';
    }
    return 'Erro do MySQL: ' . $msg;
}
