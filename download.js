/* Lista de espera do app: envia o e-mail para leads.php */
(() => {
    const form  = document.getElementById('leadForm');
    const input = document.getElementById('emailInput');
    const btn   = document.getElementById('submitBtn');
    const msgEl = document.getElementById('msg');
    if (!form) return;

    function mostrar(texto, ok) {
        msgEl.hidden = false;
        msgEl.className = 'lead-msg ' + (ok ? 'lead-msg-ok' : 'lead-msg-erro');
        msgEl.textContent = (ok ? '✓ ' : '⚠ ') + texto;
    }

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!input.checkValidity()) { mostrar('Digite um e-mail válido.', false); input.focus(); return; }
        if (!document.getElementById('consentimento').checked) { mostrar('Marque a caixa de concordância para continuar.', false); return; }

        btn.disabled = true;
        btn.textContent = 'Enviando...';
        try {
            const resp = await fetch('leads.php', {
                method: 'POST',
                headers: { Accept: 'application/json' },
                body: new FormData(form),
            });
            const data = await resp.json();
            if (data.ok) { mostrar(data.mensagem, true); form.reset(); }
            else mostrar(data.erro || 'Algo deu errado. Tente novamente.', false);
        } catch {
            mostrar('Erro de conexão. Tente novamente.', false);
        } finally {
            btn.disabled = false;
            btn.textContent = 'Avisar-me';
        }
    });
})();
