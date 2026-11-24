<?php
/**
 * TESTES FUNCIONAIS AUTOMATIZADOS — Sentrix Guard Flow
 *
 * Simula usuários reais acessando o site (como um navegador) e confere
 * se login, permissões por cargo, proteção CSRF, CRUD de dispositivos,
 * alertas, simulador, GPS, lista de espera e auditoria funcionam.
 *
 * Como rodar (com o site no ar e o banco recém-importado):
 *   php tests/teste_funcional.php http://localhost/sentrix
 *   php tests/teste_funcional.php http://localhost:8000
 *
 * Opcional: acrescente --forca-bruta para testar o bloqueio após 5 senhas
 * erradas (usa um e-mail fictício, então não bloqueia as contas reais).
 *
 * Os testes criam e alteram registros. Depois de rodar, reimporte o
 * schema.sql se quiser voltar aos dados originais.
 */

if (PHP_SAPI !== 'cli') exit("Rode pelo terminal.\n");
if (!extension_loaded('curl')) exit("A extensão curl do PHP precisa estar habilitada.\n");

$BASE = rtrim($argv[1] ?? 'http://localhost/sentrix', '/');
$TESTAR_FORCA_BRUTA = in_array('--forca-bruta', $argv, true);

/* ---------------- mini "navegador" com cookies ---------------- */
final class Navegador
{
    private string $cookies;
    public int $status = 0;
    public string $corpo = '';
    public string $local = '';

    public function __construct(private string $base)
    {
        $this->cookies = tempnam(sys_get_temp_dir(), 'sentrix');
    }

    public function get(string $caminho, array $headers = []): self
    {
        return $this->req('GET', $caminho, null, $headers);
    }

    public function post(string $caminho, array $dados, bool $comCsrf = true, array $headers = []): self
    {
        if ($comCsrf && !isset($dados['csrf_token'])) {
            $dados['csrf_token'] = $this->token();
        }
        return $this->req('POST', $caminho, $dados, $headers);
    }

    /** Pega o token CSRF da página (meta tag) usando a sessão atual. */
    public function token(): string
    {
        $r = $this->req('GET', '/privacidade.php');
        preg_match('/name="csrf-token" content="([a-f0-9]+)"/', $r->corpo, $m);
        return $m[1] ?? '';
    }

    /**
     * Login completo: e-mail + senha e, se o sistema pedir, abre o link
     * de acesso pela caixa de saída de demonstração (modo sem SMTP).
     * Ao final, $this->local aponta para painel.php se deu certo.
     */
    public function login(string $email, string $senha): self
    {
        $this->post('/login.php', ['email' => $email, 'senha' => $senha]);
        if (!str_contains($this->local, 'verificar-email.php')) {
            return $this;
        }
        $link = $this->linkDoEmail();
        if (!$link) return $this;
        $this->get('/confirmar-login.php?token=' . $link);
        return $this->post('/confirmar-login.php', ['token' => $link]);
    }

    /** Token do link mais recente na caixa de saída de demonstração. */
    public function linkDoEmail(): ?string
    {
        $caixa = $this->get('/caixa-saida.php')->corpo;
        return preg_match('/confirmar-login\.php\?token=([a-f0-9]{64})/', $caixa, $m) ? $m[1] : null;
    }

    private function req(string $metodo, string $caminho, ?array $dados = null, array $headers = []): self
    {
        $ch = curl_init($this->base . $caminho);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_COOKIEJAR      => $this->cookies,
            CURLOPT_COOKIEFILE     => $this->cookies,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 15,
        ]);
        if ($metodo === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($dados ?? []));
        }
        $this->corpo  = (string) curl_exec($ch);
        $this->status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $this->local  = (string) curl_getinfo($ch, CURLINFO_REDIRECT_URL);
        curl_close($ch);
        return $this;
    }

    /** Segue o redirecionamento (se houver) e devolve a página final. */
    public function seguir(): self
    {
        if ($this->local) {
            $caminho = parse_url($this->local, PHP_URL_PATH);
            $q = parse_url($this->local, PHP_URL_QUERY);
            $basePath = parse_url($this->base, PHP_URL_PATH) ?? '';
            $caminho = $basePath && str_starts_with($caminho, $basePath) ? substr($caminho, strlen($basePath)) : $caminho;
            return $this->get($caminho . ($q ? "?{$q}" : ''));
        }
        return $this;
    }
}

/* ---------------- utilitários de asserção ---------------- */
$ok = 0; $falhas = 0;
function teste(string $nome, bool $condicao, string $detalhe = ''): void
{
    global $ok, $falhas;
    if ($condicao) { $ok++; echo "  \033[32m✔\033[0m {$nome}\n"; }
    else { $falhas++; echo "  \033[31m✘ {$nome}\033[0m" . ($detalhe ? " — {$detalhe}" : '') . "\n"; }
}
function secao(string $t): void { echo "\n\033[1m{$t}\033[0m\n"; }

/** Lê o status de um alerta pela exportação CSV. */
function statusAlerta(Navegador $n, int $id): ?string
{
    $csv = $n->get('/exportar-alertas.php')->corpo;
    foreach (explode("\n", $csv) as $linha) {
        $c = str_getcsv(trim($linha, "\xEF\xBB\xBF\r"), ';');
        if (($c[0] ?? '') === (string) $id) return $c[4] ?? null;
    }
    return null;
}

$sufixo = substr(bin2hex(random_bytes(3)), 0, 6);
echo "Testando {$BASE}\n";

/* ======================================================= */
secao('1. Páginas públicas e proteção de páginas internas');
$anon = new Navegador($BASE);
foreach (['/index.php', '/login.php', '/cadastro.php', '/download.php', '/privacidade.php'] as $p) {
    teste("{$p} abre (200)", $anon->get($p)->status === 200, "status {$anon->status}");
}
foreach (['/painel.php', '/monitoramento.php', '/alertas-ia.php', '/gps.php', '/dispositivos.php', '/usuarios.php', '/logs.php', '/exportar-alertas.php'] as $p) {
    $anon->get($p);
    teste("{$p} exige login (redireciona)", $anon->status === 302 && str_contains($anon->local, 'login.php'));
}
$anon->get('/gps-dados.php', ['Accept: application/json']);
teste('API do GPS sem login responde 401', $anon->status === 401);
$h = get_headers($BASE . '/index.php', true);
teste('X-Frame-Options: DENY', ($h['X-Frame-Options'] ?? '') === 'DENY');

/* ======================================================= */
secao('2. Login');
$anon->post('/login.php', ['email' => 'admin@sentrixguard.com', 'senha' => 'admin123'], false);
teste('Login sem token CSRF é recusado (419)', $anon->status === 419);
$anon->login('admin@sentrixguard.com', 'senha-errada');
teste('Senha errada mostra mensagem genérica', str_contains($anon->corpo, 'E-mail ou senha incorretos'));

$admin = new Navegador($BASE);
$admin->login('admin@sentrixguard.com', 'admin123');
teste('Admin entra e é levado ao painel', $admin->status === 302 && str_contains($admin->local, 'painel.php'));
teste('Painel mostra saudação ao admin', str_contains($admin->get('/painel.php')->corpo, 'OLÁ'));

$oper = new Navegador($BASE);
$oper->login('operador@sentrixguard.com', 'operador123');
teste('Operador entra', str_contains($oper->local, 'painel.php'));

$visit = new Navegador($BASE);
$visit->login('visitante@sentrixguard.com', 'visitante123');
teste('Visualizador entra', str_contains($visit->local, 'painel.php'));

/* ======================================================= */
secao('2b. Login com link por e-mail');
$dupla = new Navegador($BASE);
$dupla->post('/login.php', ['email' => 'operador@sentrixguard.com', 'senha' => 'operador123']);
teste('Senha certa leva à etapa "confirme pelo e-mail"', str_contains($dupla->local, 'verificar-email.php'));
$dupla->get('/painel.php');
teste('Só com a senha ainda NÃO entra no painel', str_contains($dupla->local, 'login.php'));
$tk = $dupla->linkDoEmail();
teste('E-mail com o link é gerado (caixa de saída de demonstração)', (bool) $tk);
$estranho = new Navegador($BASE);
$estranho->get('/caixa-saida.php');
teste('Caixa de saída não abre para quem não digitou a senha', str_contains($estranho->local, 'login.php'));
$estranho->get('/confirmar-login.php?token=' . $tk);
teste('Abrir o link (GET) só mostra o botão — não gasta o acesso', str_contains($estranho->corpo, 'Confirmar acesso'));
$estranho->get('/confirmar-login.php?token=' . $tk);
teste('O link continua válido depois de só ser visualizado', str_contains($estranho->corpo, 'Confirmar acesso'));
$estranho->post('/confirmar-login.php', ['token' => $tk]);
teste('Confirmar no link entra no painel (ex.: no celular)', str_contains($estranho->local, 'painel.php') && str_contains($estranho->get('/painel.php')->corpo, 'Operador'));
$outro = new Navegador($BASE);
$outro->get('/confirmar-login.php?token=' . $tk);
teste('Link já usado não funciona de novo', str_contains($outro->corpo, 'Link inválido'));
$outro->get('/confirmar-login.php?token=' . str_repeat('a', 64));
teste('Link inventado é recusado', str_contains($outro->corpo, 'Link inválido'));
$outro->post('/confirmar-login.php', ['token' => $tk], false);
teste('Confirmação sem token CSRF não entra', !str_contains($outro->local, 'painel.php'));
$dupla->post('/verificar-email.php', ['acao' => 'reenviar']);
teste('Reenviar link tem intervalo mínimo', str_contains($dupla->seguir()->corpo, 'Aguarde'));
$checa = json_decode($dupla->get('/verificar-email.php?checar=1')->corpo, true);
teste('A tela de espera consulta se o login foi concluído', is_array($checa) && array_key_exists('logado', $checa));

/* ======================================================= */
secao('3. Permissões por cargo');
$alertaId = 4; // "Rosto desconhecido" (status: novo) no schema.sql
$visit->post('/alertas-ia.php', ['alerta_id' => $alertaId, 'novo_status' => 'resolvido']);
teste('Visualizador NÃO consegue resolver alerta', statusAlerta($admin, $alertaId) === 'Novo', 'status: ' . statusAlerta($admin, $alertaId));
$visit->get('/usuarios.php');
teste('Visualizador NÃO acessa Admin > Usuários', $visit->status === 403 || str_contains($visit->local, 'painel.php'));
$oper->get('/logs.php');
teste('Operador NÃO acessa o log de auditoria', str_contains($oper->local, 'painel.php'));
teste('Visualizador não vê botões de gravação', !str_contains($visit->get('/monitoramento.php')->corpo, 'toggle_gravando'));
teste('Visualizador não vê o simulador', !str_contains($visit->get('/painel.php')->corpo, 'simulador.php'));

$oper->post('/alertas-ia.php', ['alerta_id' => $alertaId, 'novo_status' => 'resolvido'], false);
teste('Ação sem token CSRF é bloqueada (419)', $oper->status === 419);
$oper->post('/alertas-ia.php', ['alerta_id' => $alertaId, 'novo_status' => 'visualizado']);
teste('Operador marca alerta como visualizado', statusAlerta($admin, $alertaId) === 'Visualizado');
$oper->post('/alertas-ia.php', ['alerta_id' => $alertaId, 'novo_status' => 'resolvido']);
teste('Operador resolve alerta', statusAlerta($admin, $alertaId) === 'Resolvido');

/* ======================================================= */
secao('4. Dispositivos (CRUD)');
$nomeCam = "Câmera Teste {$sufixo}";
$oper->post('/dispositivos.php', ['acao' => 'salvar', 'nome' => $nomeCam, 'tipo' => 'camera', 'status' => 'online',
    'localizacao' => 'Portão de Teste', 'tipo_camera' => 'fixa', 'ip_address' => '999.1.1.1']);
teste('IP inválido é rejeitado', str_contains($oper->corpo, 'Endereço IP inválido'));
$oper->post('/dispositivos.php', ['acao' => 'salvar', 'nome' => $nomeCam, 'tipo' => 'camera', 'status' => 'online',
    'localizacao' => 'Portão de Teste', 'tipo_camera' => 'ptz', 'ip_address' => '192.168.0.150', 'rtsp_url' => 'rtsp://192.168.0.150/s1']);
teste('Operador cadastra câmera', str_contains($oper->seguir()->corpo, 'cadastrado com sucesso'));
$lista = $oper->get('/dispositivos.php?busca=' . urlencode($sufixo))->corpo;
preg_match('/editar=(\d+)/', $lista, $m);
$idCam = (int) ($m[1] ?? 0);
teste('Câmera aparece na lista', $idCam > 0 && str_contains($lista, e_($nomeCam)));
teste('Câmera aparece no monitoramento', str_contains($oper->get('/monitoramento.php')->corpo, e_($nomeCam)));

$oper->post('/dispositivos.php', ['acao' => 'salvar', 'id' => $idCam, 'nome' => $nomeCam . ' (editada)', 'tipo' => 'camera',
    'status' => 'manutencao', 'tipo_camera' => 'ptz', 'ip_address' => '192.168.0.151']);
teste('Operador edita câmera', str_contains($oper->seguir()->corpo, 'editado com sucesso'));

$oper->post('/dispositivos.php', ['acao' => 'excluir', 'id' => $idCam]);
teste('Operador NÃO pode excluir', str_contains($oper->get('/dispositivos.php?busca=' . urlencode($sufixo))->corpo, "editar={$idCam}"));
$admin->post('/dispositivos.php', ['acao' => 'excluir', 'id' => $idCam]);
teste('Admin exclui dispositivo', !str_contains($admin->get('/dispositivos.php?busca=' . urlencode($sufixo))->corpo, "editar={$idCam}"));

$xss = "<script>alert({$sufixo})</script>";
$oper->post('/dispositivos.php', ['acao' => 'salvar', 'nome' => $xss, 'tipo' => 'sensor', 'status' => 'online']);
$pag = $oper->get('/dispositivos.php')->corpo;
teste('Nome com <script> é exibido escapado (sem XSS)', !str_contains($pag, $xss) && str_contains($pag, '&lt;script&gt;'));
if (preg_match('/editar=(\d+)"[^>]*>Editar<\/a>\s*<form[^>]*data-confirmar="Excluir o dispositivo &quot;&lt;script/s', $pag, $mx)) {
    $admin->post('/dispositivos.php', ['acao' => 'excluir', 'id' => (int) $mx[1]]);
}

$nomeGps = "Rastreador Teste {$sufixo}";
$oper->post('/dispositivos.php', ['acao' => 'salvar', 'nome' => $nomeGps, 'tipo' => 'gps', 'status' => 'online',
    'tipo_gps' => 'pessoa', 'gps_ativo' => '1', 'latitude' => '-22.9060', 'longitude' => '-43.1720']);
teste('Operador cadastra rastreador GPS com posição', str_contains($oper->seguir()->corpo, 'cadastrado com sucesso'));

/* ======================================================= */
secao('5. GPS, cercas, simulador e painel');
$gps = json_decode($oper->get('/gps-dados.php', ['Accept: application/json'])->corpo, true);
teste('API do GPS retorna rastreadores', ($gps['ok'] ?? false) && count($gps['rastreadores'] ?? []) >= 3);
teste('Novo rastreador aparece no mapa', in_array($nomeGps, array_column($gps['rastreadores'] ?? [], 'nome'), true));

$oper->post('/cercas.php', ['acao' => 'criar', 'nome' => "Cerca {$sufixo}", 'centro_lat' => '999', 'centro_lng' => '0', 'raio_metros' => 200]);
teste('Cerca com coordenada inválida é rejeitada', str_contains($oper->corpo, 'latitude/longitude válidas'));
$oper->post('/cercas.php', ['acao' => 'criar', 'nome' => "Cerca {$sufixo}", 'centro_lat' => '-22.9060', 'centro_lng' => '-43.1720', 'raio_metros' => 50]);
teste('Operador cria cerca virtual', str_contains($oper->seguir()->corpo, 'criada'));

$antes = json_decode($oper->get('/painel-dados.php', ['Accept: application/json'])->corpo, true);
$oper->post('/simulador.php', ['voltar' => 'painel.php']);
teste('Simulador gera eventos', str_contains($oper->seguir()->corpo, 'Simulação concluída'));
$depois = json_decode($oper->get('/painel-dados.php', ['Accept: application/json'])->corpo, true);
teste('Indicadores do painel (JSON) respondem', isset($depois['alertas_novos'], $depois['online']));
teste('Simulador criou alerta novo', ($depois['ultimo_alerta_id'] ?? 0) > ($antes['ultimo_alerta_id'] ?? 0));
$visit->post('/simulador.php', []);
teste('Visualizador NÃO roda o simulador', str_contains($visit->local, 'painel.php') && str_contains($visit->seguir()->corpo, 'não tem permissão'));

$csv = $admin->get('/exportar-alertas.php')->corpo;
teste('Exportação CSV com cabeçalho', str_starts_with($csv, "\xEF\xBB\xBF") && str_contains($csv, 'Data/hora;Nível'));

/* ======================================================= */
secao('6. Cadastro público e administração de usuários');
$novo = new Navegador($BASE);
$emailNovo = "teste{$sufixo}@exemplo.com";
$novo->post('/cadastro.php', ['nome' => 'Pessoa Teste', 'email' => $emailNovo, 'senha' => '123', 'confirmar_senha' => '123', 'aceite' => '1']);
teste('Senha fraca é rejeitada', str_contains($novo->corpo, 'mínimo 8 caracteres'));
$novo->post('/cadastro.php', ['nome' => 'Pessoa Teste', 'email' => $emailNovo, 'senha' => 'senhaForte1', 'confirmar_senha' => 'senhaForte1']);
teste('Sem aceitar a política, não cadastra', str_contains($novo->corpo, 'Política de Privacidade'));
$novo->post('/cadastro.php', ['nome' => 'Pessoa Teste', 'email' => $emailNovo, 'senha' => 'senhaForte1', 'confirmar_senha' => 'senhaForte1', 'aceite' => '1']);
teste('Cadastro válido cria conta', str_contains($novo->seguir()->corpo, 'Conta criada'));
$novo->login($emailNovo, 'senhaForte1');
teste('Conta nova entra como visualizador', str_contains($novo->get('/painel.php')->corpo, 'Visualizador'));

$usuarios = $admin->get('/usuarios.php')->corpo;
preg_match('/name="id" value="(\d+)">\s*<select name="cargo"[^>]*aria-label="Cargo de Pessoa Teste"/', $usuarios, $mu);
$idNovo = (int) ($mu[1] ?? 0);
teste('Admin vê o novo usuário', $idNovo > 0);
$admin->post('/usuarios.php', ['acao' => 'cargo', 'id' => $idNovo, 'cargo' => 'operador']);
teste('Admin promove para operador (efeito imediato)', str_contains($novo->get('/painel.php')->corpo, 'Operador'));
$admin->post('/usuarios.php', ['acao' => 'status', 'id' => $idNovo]);
$novo->get('/painel.php');
teste('Usuário desativado perde o acesso na hora', str_contains($novo->local, 'login.php'));
$admin->post('/usuarios.php', ['acao' => 'status', 'id' => 1]);
teste('Admin não consegue desativar a si mesmo', str_contains($admin->seguir()->corpo, 'própria conta'));

/* ======================================================= */
secao('7. Lista de espera (leads)');
$lead = new Navegador($BASE);
$lead->post('/leads.php', ['email' => "lead{$sufixo}@exemplo.com"], true, ['Accept: application/json']);
teste('Sem consentimento é recusado (422)', $lead->status === 422);
$lead->post('/leads.php', ['email' => 'nao-e-email', 'consentimento' => '1'], true, ['Accept: application/json']);
teste('E-mail inválido é recusado (422)', $lead->status === 422);
$lead->post('/leads.php', ['email' => "lead{$sufixo}@exemplo.com", 'consentimento' => '1'], true, ['Accept: application/json']);
teste('E-mail válido é registrado', (json_decode($lead->corpo, true)['ok'] ?? false) === true);
$lead->post('/leads.php', ['email' => "lead{$sufixo}@exemplo.com", 'consentimento' => '1'], false, ['Accept: application/json']);
teste('Sem token CSRF é recusado (419)', $lead->status === 419);

/* ======================================================= */
secao('8. Auditoria e logout');
$logs = $admin->get('/logs.php')->corpo;
teste('Log registra logins', str_contains($logs, '>login<'));
teste('Log registra falhas de login', str_contains($logs, 'login_falha'));
teste('Log registra resolução de alerta', str_contains($logs, 'alerta_resolvido'));
teste('Log registra mudança de cargo', str_contains($logs, 'usuario_cargo'));

$visit->get('/logout.php');
teste('Logout por link (GET) NÃO desloga', $visit->get('/painel.php')->status === 200);
$visit->post('/logout.php', []);
$visit->get('/painel.php');
teste('Logout pelo botão (POST) desloga', str_contains($visit->local, 'login.php'));

if ($TESTAR_FORCA_BRUTA) {
    secao('9. Proteção contra força bruta');
    $bot = new Navegador($BASE);
    $alvo = "alvo{$sufixo}@exemplo.com";
    for ($i = 0; $i < 6; $i++) $bot->login($alvo, "errada{$i}");
    teste('Após 5 senhas erradas para o mesmo e-mail, o login é bloqueado', str_contains($bot->corpo, 'Muitas tentativas'));
    $outro = new Navegador($BASE);
    teste('Outros usuários do mesmo IP continuam entrando', str_contains($outro->login('operador@sentrixguard.com', 'operador123')->local, 'painel.php'));
}

function e_(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

echo "\n" . ($falhas ? "\033[31m" : "\033[32m") . "Resultado: {$ok} passaram, {$falhas} falharam.\033[0m\n";
exit($falhas ? 1 : 0);
