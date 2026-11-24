/* Página inicial: modal de detalhes das funcionalidades + texto do celular */
(() => {
    const { esc, dados } = window.Sentrix;
    const featData = dados('featData') || [];
    const overlay  = document.getElementById('feat-overlay');
    if (!overlay) return;

    const modal      = overlay.querySelector('.feat-modal');
    const btnFechar  = overlay.querySelector('.feat-close');
    let ultimoFoco   = null;

    function abrir(idx) {
        const f = featData[idx];
        if (!f) return;
        ultimoFoco = document.activeElement;

        document.getElementById('modal-icon').innerHTML =
            `<svg viewBox="0 0 20 20" fill="currentColor" width="28" height="28" aria-hidden="true">
                <path fill-rule="evenodd" d="${esc(f.icon)}" clip-rule="evenodd"/></svg>`;
        document.getElementById('modal-badge').textContent = f.badge;
        document.getElementById('modal-title').textContent = f.title;
        document.getElementById('modal-desc').textContent  = f.desc;
        document.getElementById('modal-list').innerHTML = f.items.map(([texto, disponivel]) =>
            `<li class="${disponivel ? 'item-ok' : 'item-plan'}">${esc(texto)}
                ${disponivel ? '' : '<span class="tag-plan">planejado</span>'}</li>`
        ).join('');

        overlay.hidden = false;
        document.body.style.overflow = 'hidden';
        btnFechar.focus();
    }

    function fechar() {
        overlay.hidden = true;
        document.body.style.overflow = '';
        if (ultimoFoco) ultimoFoco.focus();
    }

    document.querySelectorAll('.feature-card[data-feature]').forEach(btn => {
        btn.addEventListener('click', () => abrir(Number(btn.dataset.feature)));
    });
    btnFechar.addEventListener('click', fechar);
    overlay.addEventListener('click', e => { if (e.target === overlay) fechar(); });

    // Esc fecha; Tab fica preso dentro do modal (acessibilidade)
    document.addEventListener('keydown', e => {
        if (overlay.hidden) return;
        if (e.key === 'Escape') { fechar(); return; }
        if (e.key === 'Tab') {
            const focaveis = modal.querySelectorAll('button, a[href]');
            const primeiro = focaveis[0], ultimo = focaveis[focaveis.length - 1];
            if (e.shiftKey && document.activeElement === primeiro) { e.preventDefault(); ultimo.focus(); }
            else if (!e.shiftKey && document.activeElement === ultimo) { e.preventDefault(); primeiro.focus(); }
        }
    });

    // Texto alternando no círculo do celular ilustrativo
    const statusCore = document.querySelector('.status-core');
    if (statusCore && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        const rotulos = ['ATIVO', 'SEGURO', 'ONLINE'];
        let i = 0;
        setInterval(() => { i = (i + 1) % rotulos.length; statusCore.textContent = rotulos[i]; }, 3000);
    }
})();
