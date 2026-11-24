/* Painel: atualiza os indicadores a cada 15 s sem recarregar a página */
(() => {
    const inicial = window.Sentrix.dados('dadosPainel') || {};
    let ultimoAlertaId = inicial.ultimo_alerta_id || 0;
    const aviso = document.getElementById('avisoNovos');

    async function atualizar() {
        if (document.hidden) return; // não consulta com a aba em segundo plano
        try {
            const resp = await fetch('painel-dados.php', { headers: { Accept: 'application/json' } });
            if (resp.status === 401) { window.location.href = 'login.php'; return; }
            const d = await resp.json();
            if (!d.ok) return;
            document.querySelectorAll('[data-indicador]').forEach(el => {
                const valor = d[el.dataset.indicador];
                if (valor !== undefined && el.textContent !== String(valor)) {
                    el.textContent = valor;
                    el.classList.remove('stat-pulse');
                    void el.offsetWidth; // reinicia a animação
                    el.classList.add('stat-pulse');
                }
            });
            if (d.ultimo_alerta_id > ultimoAlertaId && aviso) aviso.hidden = false;
        } catch (e) { /* sem conexão: tenta de novo no próximo ciclo */ }
    }
    setInterval(atualizar, 15000);
})();
