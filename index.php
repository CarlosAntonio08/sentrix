<?php
require_once 'includes/bootstrap.php';
$titulo = 'Sentrix Guard Flow — Segurança Inteligente';

/*
 * Funcionalidades exibidas na página inicial.
 * Cada item indica se já está implementado nesta versão (true)
 * ou se é uma evolução planejada (false) — assim o site mostra
 * com transparência o que o sistema realmente faz hoje.
 */
$features = [
    [
        'title' => 'Monitoramento',
        'badge' => 'Câmeras e sensores',
        'icon'  => 'M15 10a5 5 0 1 1-10 0 5 5 0 0 1 10 0ZM2 10C2 5.58 5.58 2 10 2s8 3.58 8 8-3.58 8-8 8-8-3.58-8-8Zm8-2a2 2 0 1 0 0 4 2 2 0 0 0 0-4Z',
        'resumo'=> 'Status de câmeras, sensores e alarmes em um só lugar.',
        'desc'  => 'Acompanhe o status de todos os equipamentos cadastrados e controle a gravação das câmeras diretamente pelo painel web.',
        'items' => [
            ['Status online/offline/manutenção de cada dispositivo', true],
            ['Iniciar e parar gravação pelo painel', true],
            ['Indicadores atualizados automaticamente', true],
            ['Transmissão de vídeo ao vivo no navegador', false],
        ],
    ],
    [
        'title' => 'Gestão de Câmeras',
        'badge' => 'PTZ & Fixas',
        'icon'  => 'M3 6a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v2l3.447-2.298A1 1 0 0 1 20 6.535v6.93a1 1 0 0 1-1.553.832L15 12v2a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V6Z',
        'resumo'=> 'Cadastro completo de câmeras fixas e PTZ.',
        'desc'  => 'Cadastre câmeras com modelo, tipo, resolução, endereço IP e URL de stream RTSP — base para a integração com equipamentos reais.',
        'items' => [
            ['Cadastro, edição e exclusão de câmeras', true],
            ['Registro de IP e URL RTSP', true],
            ['Controle pan-tilt-zoom remoto', false],
            ['Compatibilidade com o padrão ONVIF', false],
        ],
    ],
    [
        'title' => 'Alertas com IA',
        'badge' => 'Detecção',
        'icon'  => 'M14.857 17c.132.088.24.2.315.333.076.133.112.285.112.439 0 .497-.196.973-.546 1.324a1.852 1.852 0 0 1-1.323.547 1.852 1.852 0 0 1-1.323-.547A1.878 1.878 0 0 1 11.546 18c0-.154.037-.306.112-.44a.907.907 0 0 1 .316-.332h2.883ZM12.415 3c.276 0 .5.224.5.5v.763a6.503 6.503 0 0 1 5.5 6.424v1.638l.83 1.66c.173.346.265.729.265 1.118 0 .414-.337.75-.75.75H3.24a.75.75 0 0 1-.75-.75c0-.39.092-.772.265-1.118l.83-1.66V10.688A6.503 6.503 0 0 1 9.085 4.263V3.5c0-.276.224-.5.5-.5h2.83Z',
        'resumo'=> 'Detecções com tipo e grau de confiança.',
        'desc'  => 'Cada alerta gerado por IA registra o tipo de detecção (pessoa, veículo, rosto desconhecido…) e o percentual de confiança. Nesta versão, as detecções são geradas por um simulador para demonstração.',
        'items' => [
            ['Registro de detecções com tipo e % de confiança', true],
            ['Simulador de detecções para demonstração', true],
            ['Modelo de visão computacional real nas câmeras', false],
            ['Notificações por push, e-mail e SMS', false],
        ],
    ],
    [
        'title' => 'GPS e Cercas',
        'badge' => 'Rastreamento',
        'icon'  => 'M10 2a6 6 0 0 1 6 6c0 4.314-5.188 9.24-5.411 9.447a.818.818 0 0 1-1.178 0C9.188 17.24 4 12.314 4 8a6 6 0 0 1 6-6Zm0 4a2 2 0 1 0 0 4 2 2 0 0 0 0-4Z',
        'resumo'=> 'Mapa ao vivo, trilhas e cercas virtuais.',
        'desc'  => 'Acompanhe veículos, equipes e ativos em um mapa interativo que se atualiza sozinho. Cercas virtuais geram alertas automáticos quando um rastreador sai da área.',
        'items' => [
            ['Mapa interativo com atualização automática', true],
            ['Trilha recente e histórico de posições', true],
            ['Cercas virtuais com alerta automático de saída', true],
            ['Integração com rastreadores físicos', false],
        ],
    ],
    [
        'title' => 'Painel Administrativo',
        'badge' => 'Gestão',
        'icon'  => 'M3 3h7v7H3V3Zm0 11h7v7H3v-7Zm11-11h7v7h-7V3Zm0 11h7v7h-7v-7Z',
        'resumo'=> 'Indicadores, usuários, cargos e auditoria.',
        'desc'  => 'Indicadores em tempo real, controle de acesso por cargo (administrador, operador e visualizador) e registro de auditoria de todas as ações.',
        'items' => [
            ['Indicadores com atualização automática', true],
            ['Gestão de usuários e permissões por cargo', true],
            ['Log de auditoria com filtros', true],
            ['Login com confirmação por link no e-mail', true],
            ['Relatórios em PDF com gráficos', false],
        ],
    ],
    [
        'title' => 'Gestão de Alertas',
        'badge' => 'Crítico',
        'icon'  => 'M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92ZM11 13a1 1 0 1 1-2 0 1 1 0 0 1 2 0Zm-1-8a1 1 0 0 1 1 1v3a1 1 0 1 1-2 0V6a1 1 0 0 1 1-1Z',
        'resumo'=> 'Níveis, status, filtros e exportação.',
        'desc'  => 'Alertas classificados por nível (baixo, médio, crítico) e acompanhados do início ao fim: novo, visualizado e resolvido — tudo registrado na auditoria.',
        'items' => [
            ['Filtros por status e nível', true],
            ['Fluxo novo → visualizado → resolvido', true],
            ['Exportação para planilha (CSV)', true],
            ['Escalonamento automático de incidentes', false],
        ],
    ],
];

$scriptsPagina = ['index.js'];
require_once 'includes/header.php';
?>

<main>
<!-- HERO -->
<section id="inicio" class="hero">
    <div class="hero-gradient"></div>
    <div class="hero-orb"></div>

    <div class="hero-inner">
        <div>
            <p class="hero-label reveal">SEGURANÇA INTELIGENTE</p>
            <h1 class="hero-title reveal reveal-delay-1">
                SENTRIX
                <span class="accent">GUARD FLOW</span>
            </h1>
            <p class="hero-desc reveal reveal-delay-2">
                Plataforma web de monitoramento para segurança residencial e empresarial:
                câmeras, sensores, rastreamento GPS e alertas inteligentes em um só painel.
            </p>
            <div class="hero-btns reveal reveal-delay-3">
                <a class="btn-primary" href="<?= !empty($_SESSION['usuario_id']) ? 'painel.php' : 'cadastro.php' ?>">Começar Agora</a>
                <a class="btn-outline" href="#funcionalidades">Ver Funcionalidades</a>
            </div>
        </div>

        <div class="phone-wrap" aria-hidden="true">
            <div class="phone-glow"></div>
            <div class="phone reveal reveal-delay-2">
                <div class="phone-inner">
                    <div class="phone-header">
                        <div class="phone-brand">SENTRIX</div>
                        <div class="phone-sub">GUARD FLOW</div>
                    </div>
                    <div class="phone-status">
                        <div class="status-ring">
                            <div class="status-core">ATIVO</div>
                        </div>
                    </div>
                    <div class="phone-grid">
                        <div class="phone-card"><span class="phone-card-label">Alertas</span><span class="phone-card-val">Em tempo real</span></div>
                        <div class="phone-card"><span class="phone-card-label">Câmeras</span><span class="phone-card-val">Status</span></div>
                        <div class="phone-card"><span class="phone-card-label">GPS</span><span class="phone-card-val">Mapa ao vivo</span></div>
                        <div class="phone-card"><span class="phone-card-label">IA</span><span class="phone-card-val">Detecções</span></div>
                    </div>
                    <div class="phone-cta"><span>Abrir Painel</span></div>
                </div>
            </div>
            <p class="phone-legenda">Ilustração do aplicativo</p>
        </div>
    </div>
</section>

<!-- SOBRE -->
<section id="sobre" class="section-sobre">
    <div class="sobre-inner">
        <div>
            <p class="section-label reveal">TECNOLOGIA</p>
            <h2 class="section-title reveal reveal-delay-1">
                Segurança moderna
                <span class="accent">em um só painel.</span>
            </h2>
            <p class="sobre-text reveal reveal-delay-2">
                O Sentrix une monitoramento de dispositivos, rastreamento GPS e alertas
                classificados por inteligência artificial em uma plataforma única.
            </p>
            <p class="sobre-text texto-dim reveal reveal-delay-3">
                O painel web funciona no computador e no celular, com controle de acesso por cargo
                e registro de auditoria de todas as ações.
            </p>
        </div>

        <div class="cards-grid">
            <?php
            $cards = [
                ['Monitoramento', 'Câmeras, sensores e gravação.',      'monitoramento.php'],
                ['Alertas IA',    'Detecções classificadas por nível.', 'alertas-ia.php'],
                ['GPS',           'Mapa ao vivo e cercas virtuais.',     'gps.php'],
                ['Painel',        'Indicadores e administração.',        'painel.php'],
            ];
            foreach ($cards as $i => $card): ?>
            <a href="<?= e($card[2]) ?>" class="card reveal reveal-delay-<?= $i + 1 ?>">
                <div class="card-icon"></div>
                <h3 class="card-title"><?= e($card[0]) ?></h3>
                <p class="card-desc"><?= e($card[1]) ?></p>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- FUNCIONALIDADES -->
<section id="funcionalidades" class="section-features">
    <div class="features-inner">
        <div class="features-header">
            <p class="section-label reveal">RECURSOS DO SISTEMA</p>
            <h2 class="section-title reveal reveal-delay-1">Funcionalidades</h2>
            <p class="features-legenda reveal reveal-delay-2">
                <span class="legenda-ok">✓ disponível nesta versão</span>
                <span class="legenda-plan">◷ planejado</span>
            </p>
        </div>

        <div class="features-grid">
            <?php foreach ($features as $i => $f): ?>
            <button type="button" class="feature-card reveal reveal-delay-<?= ($i % 3) + 1 ?>"
                    data-feature="<?= $i ?>" aria-haspopup="dialog">
                <span class="feature-icon">
                    <svg viewBox="0 0 20 20" fill="currentColor" width="28" height="28" aria-hidden="true">
                        <path fill-rule="evenodd" d="<?= e($f['icon']) ?>" clip-rule="evenodd"/>
                    </svg>
                </span>
                <span class="feature-title"><?= e($f['title']) ?></span>
                <span class="feature-desc"><?= e($f['resumo']) ?></span>
                <span class="feature-cta">Ver detalhes <span aria-hidden="true">→</span></span>
            </button>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- MODAL DE DETALHES -->
<div id="feat-overlay" class="feat-overlay" hidden>
    <div class="feat-modal" role="dialog" aria-modal="true" aria-labelledby="modal-title">
        <button type="button" class="feat-close" aria-label="Fechar">
            <svg viewBox="0 0 20 20" fill="currentColor" width="20" height="20" aria-hidden="true">
                <path d="M4.293 4.293a1 1 0 0 1 1.414 0L10 8.586l4.293-4.293a1 1 0 1 1 1.414 1.414L11.414 10l4.293 4.293a1 1 0 0 1-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 0 1-1.414-1.414L8.586 10 4.293 5.707a1 1 0 0 1 0-1.414Z"/>
            </svg>
        </button>
        <div class="feat-modal-icon" id="modal-icon" aria-hidden="true"></div>
        <span class="feat-badge" id="modal-badge"></span>
        <h2 class="feat-modal-title" id="modal-title"></h2>
        <p class="feat-modal-desc" id="modal-desc"></p>
        <ul class="feat-modal-list" id="modal-list"></ul>
        <a class="btn-primary feat-modal-btn" href="<?= !empty($_SESSION['usuario_id']) ? 'painel.php' : 'cadastro.php' ?>">Começar Agora</a>
    </div>
</div>

<!-- APLICATIVO -->
<section id="app" class="section-sobre section-app">
    <div class="app-inner">
        <p class="section-label reveal">DISPONÍVEL AGORA</p>
        <h2 class="section-title reveal reveal-delay-1">
            Instale o
            <span class="accent">Aplicativo</span>
        </h2>
        <p class="sobre-text reveal reveal-delay-2">
            Leve o Sentrix para o celular ou computador: ícone na tela inicial, tela cheia e
            acesso confirmado pelo seu e-mail. Sem loja de aplicativos.
        </p>
        <div class="hero-btns hero-btns-centro reveal reveal-delay-3">
            <button type="button" class="btn-primary" data-instalar-app hidden>⤓ Instalar agora</button>
            <a class="btn-outline" href="download.php">Como instalar</a>
        </div>
    </div>
</section>
</main>

<script type="application/json" id="featData"><?= json_encode(array_map(fn ($f) => [
    'title' => $f['title'], 'badge' => $f['badge'], 'icon' => $f['icon'],
    'desc'  => $f['desc'],  'items' => $f['items'],
], $features), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>

<?php require_once 'includes/footer.php'; ?>
