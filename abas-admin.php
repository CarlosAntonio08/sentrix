<?php
/** Abas da área de administração (usuarios.php, logs.php, configuracoes-email.php). */
$abaAtual = basename($_SERVER['PHP_SELF']);
$abas = [
    'usuarios.php'            => 'Usuários',
    'logs.php'                => 'Log de auditoria',
    'configuracoes-email.php' => 'E-mail',
    'instalar.php'            => 'Dados de demonstração',
];
?>
<nav class="tabs" aria-label="Administração">
  <?php foreach ($abas as $arquivo => $rotuloAba): ?>
    <a href="<?= $arquivo ?>" class="tab <?= $abaAtual === $arquivo ? 'active' : '' ?>"><?= e($rotuloAba) ?></a>
  <?php endforeach; ?>
</nav>
