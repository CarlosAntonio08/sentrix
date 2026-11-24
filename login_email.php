<?php
/**
 * Login em duas etapas com link por e-mail ("link mágico").
 *
 *  1. A pessoa informa e-mail e senha (login.php).
 *  2. Se a senha estiver certa, o sistema gera um token aleatório de 256 bits,
 *     guarda só o HASH dele no banco e envia o link por e-mail.
 *  3. Ao abrir o link (confirmar-login.php), o token é conferido, marcado como
 *     usado e a sessão é criada — no navegador ou no app onde o link foi aberto.
 *
 * Segurança: o link vale por LOGIN_LINK_MINUTOS, só pode ser usado uma vez,
 * e um link novo invalida os anteriores.
 */
require_once __DIR__ . '/email.php';

/** Cria a sessão autenticada (usada após confirmar o link ou, sem confirmação, após a senha). */
function finalizar_login(PDO $pdo, array $usuario, string $como = 'senha'): void
{
    session_regenerate_id(true);
    unset($_SESSION['login_pendente'], $_SESSION['csrf_token']);
    $_SESSION['usuario_id']    = (int) $usuario['id'];
    $_SESSION['usuario_nome']  = $usuario['nome'];
    $_SESSION['usuario_cargo'] = $usuario['cargo'];
    $_SESSION['ultimo_acesso'] = time();

    $pdo->prepare('UPDATE usuarios SET ultimo_login = NOW() WHERE id = ?')->execute([$usuario['id']]);
    registrar_log($pdo, (int) $usuario['id'], 'login',
        $como === 'link' ? 'Login confirmado pelo link enviado por e-mail' : 'Login realizado com sucesso');
}

/** Gera um token novo para o usuário (invalidando os anteriores) e devolve o token em texto. */
function criar_token_login(PDO $pdo, int $usuarioId): string
{
    $pdo->prepare('UPDATE login_tokens SET expira_em = NOW() WHERE usuario_id = ? AND usado_em IS NULL AND expira_em > NOW()')
        ->execute([$usuarioId]);

    $token = bin2hex(random_bytes(32));
    $pdo->prepare(
        'INSERT INTO login_tokens (usuario_id, token_hash, expira_em, ip_origem)
         VALUES (?, ?, NOW() + INTERVAL ' . (int) LOGIN_LINK_MINUTOS . ' MINUTE, ?)'
    )->execute([$usuarioId, hash('sha256', $token), ip_cliente()]);

    return $token;
}

/** Envia o e-mail com o link de acesso. Retorna o resultado de enviar_email(). */
function enviar_link_login(PDO $pdo, array $usuario): array
{
    $token = criar_token_login($pdo, (int) $usuario['id']);
    $link  = url_base() . 'confirmar-login.php?token=' . $token;
    $nome  = htmlspecialchars(explode(' ', trim($usuario['nome']))[0], ENT_QUOTES, 'UTF-8');
    $quando = date('d/m/Y \à\s H:i');
    $ip = htmlspecialchars(ip_cliente(), ENT_QUOTES, 'UTF-8');
    $min = (int) LOGIN_LINK_MINUTOS;

    $html = modelo_email(
        'Confirme seu acesso',
        "Olá, {$nome}!<br><br>Recebemos um pedido de acesso à sua conta no Sentrix em {$quando}."
        . " Para entrar, toque no botão abaixo. O link vale por <strong style=\"color:#fff\">{$min} minutos</strong> e só pode ser usado uma vez.",
        'Entrar no Sentrix',
        $link,
        "Não foi você? Ignore este e-mail — sem o link, ninguém entra na sua conta. "
        . "Se isso se repetir, troque sua senha.<br>Pedido feito do IP {$ip}.<br><br>"
    );
    $texto = "Olá, {$nome}!\n\nPara entrar no Sentrix, abra este link (válido por {$min} minutos, uso único):\n{$link}\n\n"
        . "Não foi você? Ignore este e-mail.";

    $r = enviar_email($pdo, (int) $usuario['id'], $usuario['email'], 'Seu link de acesso ao Sentrix', $html, $texto);
    registrar_log($pdo, (int) $usuario['id'], 'login_link_enviado',
        'Link de acesso enviado para ' . mascarar_email($usuario['email']) . " ({$r['status']})");
    return $r;
}

/**
 * Procura um token válido (não usado, não expirado, usuário ativo).
 * Não consome o token — use consumir_token() para isso.
 */
function buscar_token_valido(PDO $pdo, string $token): ?array
{
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) return null;
    $stmt = $pdo->prepare(
        'SELECT t.id AS token_id, u.id, u.nome, u.email, u.cargo
         FROM login_tokens t JOIN usuarios u ON u.id = t.usuario_id
         WHERE t.token_hash = ? AND t.usado_em IS NULL AND t.expira_em > NOW() AND u.ativo = 1'
    );
    $stmt->execute([hash('sha256', $token)]);
    return $stmt->fetch() ?: null;
}

/** Marca o token como usado. Retorna false se outra requisição já o usou. */
function consumir_token(PDO $pdo, int $tokenId): bool
{
    $stmt = $pdo->prepare('UPDATE login_tokens SET usado_em = NOW() WHERE id = ? AND usado_em IS NULL AND expira_em > NOW()');
    $stmt->execute([$tokenId]);
    return $stmt->rowCount() === 1;
}

/**
 * A caixa de saída de demonstração pode ser aberta?
 *  - modo demonstração (e-mail não configurado): sim;
 *  - envio falhou: só no próprio computador que roda o sistema (reserva para a
 *    apresentação). Assim, quem sabe só a senha não consegue pular o e-mail.
 */
function caixa_saida_permitida(array $pendente): bool
{
    if (!email_configurado()) return true;
    $local = in_array(ip_cliente(), ['127.0.0.1', '::1'], true);
    return ($pendente['status'] ?? '') === 'falhou' && $local;
}

