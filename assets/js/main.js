/* ============================================
   SENTRIX GUARD FLOW — main.js (todas as páginas)
   ============================================ */

// --- Navbar: fundo mais escuro ao rolar ---
const navbar = document.getElementById('navbar');
if (navbar) {
    window.addEventListener('scroll', () => {
        navbar.classList.toggle('scrolled', window.scrollY > 40);
    }, { passive: true });
}

// --- Menu mobile ---
const menuToggle = document.getElementById('menuToggle');
const mobileMenu = document.getElementById('mobileMenu');
if (menuToggle && mobileMenu) {
    menuToggle.addEventListener('click', () => {
        const aberto = mobileMenu.classList.toggle('open');
        menuToggle.setAttribute('aria-expanded', String(aberto));
        menuToggle.setAttribute('aria-label', aberto ? 'Fechar menu' : 'Abrir menu');
    });
    const fecharMenu = () => {
        if (!mobileMenu.classList.contains('open')) return;
        mobileMenu.classList.remove('open');
        menuToggle.setAttribute('aria-expanded', 'false');
        menuToggle.setAttribute('aria-label', 'Abrir menu');
    };
    mobileMenu.querySelectorAll('a').forEach(link => {
        link.addEventListener('click', fecharMenu);
    });
    // Fecha com a tecla Esc, ao tocar fora do menu ou ao voltar para tela larga
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') fecharMenu(); });
    document.addEventListener('click', (e) => {
        if (navbar && !navbar.contains(e.target)) fecharMenu();
    });
    window.matchMedia('(min-width: 1151px)').addEventListener('change', (e) => { if (e.matches) fecharMenu(); });
}

// --- Animação de entrada ao rolar (respeita "reduzir movimento") ---
const revealEls = document.querySelectorAll('.reveal');
const reduzirMovimento = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
if (reduzirMovimento || !('IntersectionObserver' in window)) {
    revealEls.forEach(el => el.classList.add('visible'));
} else {
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.12 });
    revealEls.forEach(el => observer.observe(el));
}

// --- Confirmação antes de ações destrutivas (form[data-confirmar]) ---
document.querySelectorAll('form[data-confirmar]').forEach(form => {
    form.addEventListener('submit', (e) => {
        if (!window.confirm(form.dataset.confirmar)) e.preventDefault();
    });
});

// --- Select que envia o formulário ao mudar (ex.: cargo do usuário) ---
document.querySelectorAll('select[data-autosubmit]').forEach(sel => {
    sel.addEventListener('change', () => sel.form.submit());
});

// --- Formulário de dispositivo: mostra só os campos do tipo escolhido ---
const selectTipo = document.querySelector('#formDispositivo #tipo');
if (selectTipo) {
    const atualizarCampos = () => {
        document.querySelectorAll('#formDispositivo fieldset[data-tipo]').forEach(fs => {
            const visivel = fs.dataset.tipo === selectTipo.value;
            fs.hidden = !visivel;
            fs.disabled = !visivel;
        });
    };
    selectTipo.addEventListener('change', atualizarCampos);
    atualizarCampos();
}

// --- Utilitários compartilhados pelos scripts das páginas ---
window.Sentrix = {
    /** Escapa texto antes de inserir como HTML. */
    esc(str) {
        return String(str ?? '').replace(/[&<>"']/g, c => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        }[c]));
    },
    /** Lê um bloco <script type="application/json" id="..."> da página. */
    dados(id) {
        const el = document.getElementById(id);
        try { return el ? JSON.parse(el.textContent) : null; } catch { return null; }
    },
    csrf() {
        return document.querySelector('meta[name="csrf-token"]')?.content || '';
    },
};

/* ============================================
   APP INSTALÁVEL (PWA)
   ============================================ */
// Registra o service worker (só funciona em https:// ou em localhost/127.0.0.1)
if ('serviceWorker' in navigator && (location.protocol === 'https:' || ['localhost', '127.0.0.1'].includes(location.hostname))) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('sw.js').catch(() => { /* sem PWA, o site segue normal */ });
    });
}

const modoApp = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
if (modoApp) document.documentElement.classList.add('modo-app');

// Botões "Instalar app" (elementos com data-instalar-app) aparecem quando o navegador permite
let pedidoInstalacao = null;
const botoesInstalar = () => document.querySelectorAll('[data-instalar-app]');
window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault();
    pedidoInstalacao = e;
    botoesInstalar().forEach((b) => { b.hidden = false; });
    document.querySelectorAll('[data-instalar-manual]').forEach((el) => { el.hidden = true; });
});
document.addEventListener('click', async (e) => {
    const botao = e.target.closest('[data-instalar-app]');
    if (!botao || !pedidoInstalacao) return;
    pedidoInstalacao.prompt();
    await pedidoInstalacao.userChoice;
    pedidoInstalacao = null;
    botoesInstalar().forEach((b) => { b.hidden = true; });
});
window.addEventListener('appinstalled', () => {
    botoesInstalar().forEach((b) => { b.hidden = true; });
    document.querySelectorAll('[data-app-instalado]').forEach((el) => { el.hidden = false; });
});
document.querySelectorAll('[data-se-app]').forEach((el) => { el.hidden = !modoApp; });
document.querySelectorAll('[data-se-navegador]').forEach((el) => { el.hidden = modoApp; });
