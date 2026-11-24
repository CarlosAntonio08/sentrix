<?php
/**
 * Envio de e-mails (PHPMailer + SMTP, ex.: Gmail) e modelo visual dos e-mails.
 *
 * Todo e-mail fica registrado na tabela emails_enviados. Quando o SMTP não está
 * configurado, o e-mail não sai de verdade (status "demonstracao") e pode ser
 * visto na caixa de saída de demonstração — útil para apresentar sem internet.
 */
require_once __DIR__ . '/../lib/PHPMailer/Exception.php';
require_once __DIR__ . '/../lib/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/../lib/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;

function email_configurado(): bool
{
    return MAIL_USUARIO !== '' && MAIL_SENHA !== '';
}

/** Endereço base do site para montar links (ex.: http://127.0.0.1:8000/). */
function url_base(): string
{
    if (APP_URL !== '') {
        return rtrim(APP_URL, '/') . '/';
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    $host  = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $pasta = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    return ($https ? 'https' : 'http') . '://' . $host . $pasta . '/';
}

/**
 * Envia um e-mail e registra no histórico.
 * Retorna ['status' => 'enviado'|'falhou'|'demonstracao', 'erro' => ?string].
 */
function enviar_email(PDO $pdo, ?int $usuarioId, string $para, string $assunto, string $html, string $texto): array
{
    $status = 'demonstracao';
    $erro   = null;

    if (email_configurado()) {
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host     = MAIL_SMTP_HOST;
            $mail->Port     = (int) MAIL_SMTP_PORTA;
            $mail->SMTPAuth = true;
            $mail->Username = MAIL_USUARIO;
            $mail->Password = MAIL_SENHA;
            // Limita a espera: um servidor de e-mail lento não pode travar o site
            $mail->Timeout  = 12;
            $mail->getSMTPInstance()->Timelimit = 12;
            if ((int) MAIL_SMTP_PORTA === 465) {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } elseif ((int) MAIL_SMTP_PORTA === 587) {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } else {
                $mail->SMTPAutoTLS = false; // outras portas: servidores de teste locais
            }
            $mail->CharSet = PHPMailer::CHARSET_UTF8;
            $mail->setFrom(MAIL_USUARIO, MAIL_REMETENTE_NOME);
            $mail->addAddress($para);
            $mail->Subject = $assunto;
            $mail->isHTML(true);
            $mail->Body    = $html;
            $mail->AltBody = $texto;
            $mail->send();
            $status = 'enviado';
        } catch (Throwable $e) {
            $status = 'falhou';
            $erro   = mb_substr($mail->ErrorInfo ?: $e->getMessage(), 0, 255);
            error_log('[Sentrix] Falha ao enviar e-mail: ' . $erro);
        }
    }

    $pdo->prepare(
        'INSERT INTO emails_enviados (usuario_id, destinatario, assunto, corpo_html, status, erro) VALUES (?, ?, ?, ?, ?, ?)'
    )->execute([$usuarioId, $para, $assunto, $html, $status, $erro]);

    return ['status' => $status, 'erro' => $erro];
}

/** Explica em português os erros de SMTP mais comuns. */
function explicar_erro_email(?string $erro): string
{
    $erro = (string) $erro;
    if (stripos($erro, 'authenticate') !== false || stripos($erro, 'Username and Password') !== false) {
        return 'O Gmail recusou o login. Confira o e-mail e use uma "senha de app" de 16 letras (não a senha normal da conta).';
    }
    if (stripos($erro, 'connect') !== false) {
        return 'Não foi possível conectar ao servidor de e-mail. Verifique a internet do computador.';
    }
    return 'Falha no envio do e-mail' . ($erro ? ": {$erro}" : '.');
}

/** Esconde parte do e-mail: anamaria@gmail.com → an*****a@gmail.com */
function mascarar_email(string $email): string
{
    [$nome, $dominio] = array_pad(explode('@', $email, 2), 2, '');
    $tam = mb_strlen($nome);
    $visivel = $tam <= 3 ? mb_substr($nome, 0, 1) . str_repeat('*', max($tam - 1, 1))
        : mb_substr($nome, 0, 2) . str_repeat('*', $tam - 3) . mb_substr($nome, -1);
    return $visivel . '@' . $dominio;
}

/** Modelo HTML dos e-mails do Sentrix (estilos inline, compatível com Gmail/Outlook). */
function modelo_email(string $titulo, string $paragrafosHtml, ?string $textoBotao = null, ?string $urlBotao = null, string $rodapeHtml = ''): string
{
    $tituloE = htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8');
    $botao = '';
    if ($textoBotao && $urlBotao) {
        $u = htmlspecialchars($urlBotao, ENT_QUOTES, 'UTF-8');
        $t = htmlspecialchars($textoBotao, ENT_QUOTES, 'UTF-8');
        $botao = <<<HTML
<tr><td align="center" style="padding:8px 0 28px">
  <a href="{$u}" style="display:inline-block;background:#dc2626;color:#ffffff;text-decoration:none;font-weight:bold;font-size:16px;letter-spacing:1px;text-transform:uppercase;padding:16px 36px;border-radius:12px">{$t}</a>
</td></tr>
<tr><td style="font-size:12px;color:#71717a;line-height:1.6;padding-bottom:8px">Se o botão não funcionar, copie e cole este endereço no navegador:<br><a href="{$u}" style="color:#dc2626;word-break:break-all">{$u}</a></td></tr>
HTML;
    }
    return <<<HTML
<!DOCTYPE html>
<html lang="pt-br"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>{$tituloE}</title></head>
<body style="margin:0;padding:0;background:#0b0b0d">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#0b0b0d;padding:32px 12px;font-family:Arial,Helvetica,sans-serif">
<tr><td align="center">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;background:#18181b;border:1px solid #27272a;border-radius:18px;padding:36px 32px">
    <tr><td style="padding-bottom:24px">
      <div style="font-size:28px;letter-spacing:6px;color:#dc2626;font-weight:bold">SENTRIX</div>
      <div style="font-size:10px;letter-spacing:5px;color:#a1a1aa">GUARD FLOW</div>
    </td></tr>
    <tr><td style="font-size:22px;color:#ffffff;font-weight:bold;padding-bottom:16px">{$tituloE}</td></tr>
    <tr><td style="font-size:15px;color:#d4d4d8;line-height:1.7;padding-bottom:20px">{$paragrafosHtml}</td></tr>
    {$botao}
    <tr><td style="border-top:1px solid #27272a;padding-top:18px;font-size:12px;color:#71717a;line-height:1.6">{$rodapeHtml}
      Este é um e-mail automático do Sentrix Guard Flow (projeto acadêmico — TCC). Não responda.</td></tr>
  </table>
</td></tr>
</table>
</body></html>
HTML;
}
