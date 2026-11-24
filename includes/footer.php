<footer class="footer">
    <div class="footer-inner">
        <h2 class="footer-logo">SENTRIX</h2>
        <p class="footer-desc">Plataforma de monitoramento e proteção inteligente.</p>
        <div class="footer-tags">
            <span>Segurança</span>
            <span class="dot">•</span>
            <span>Monitoramento</span>
            <span class="dot">•</span>
            <span>Tecnologia</span>
        </div>
        <p class="footer-links"><a href="privacidade.php">Política de Privacidade</a></p>
        <p class="footer-copy">&copy; <?= date('Y') ?> Sentrix Guard Flow. Projeto acadêmico (TCC).</p>
    </div>
</footer>

<script src="assets/js/main.js"></script>
<?php if (!empty($usarMapa)): ?>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<?php endif; ?>
<?php if (!empty($scriptsPagina)): ?>
<?php foreach ($scriptsPagina as $s): ?>
<script src="assets/js/<?= e($s) ?>"></script>
<?php endforeach; ?>
<?php endif; ?>
</body>
</html>
