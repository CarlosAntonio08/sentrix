/* Etapa 2 do login: se o link for aberto em outra aba deste navegador,
   esta página percebe e segue sozinha para o painel. */
(() => {
    const checar = async () => {
        if (document.hidden) return;
        try {
            const r = await fetch('verificar-email.php?checar=1', { headers: { Accept: 'application/json' } });
            const d = await r.json();
            if (d.logado) window.location.href = 'painel.php';
        } catch { /* tenta de novo */ }
    };
    setInterval(checar, 3000);
    document.addEventListener('visibilitychange', checar);
})();
