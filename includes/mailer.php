<?php
/**
 * includes/mailer.php
 *
 * Envio de e-mails via SMTP usando PHPMailer (instalado via Composer —
 * ver composer.json / vendor/). Implementa a função enviarCodigoVerificacao(),
 * já usada por verificacoes/verificar_email.php e por recuperar_senha.php,
 * com a assinatura descoberta nesses dois chamadores:
 *
 *     enviarCodigoVerificacao(string $email, string $nome, string $codigo): bool
 *
 * Credenciais SMTP vêm de variáveis de ambiente (arquivo .env na raiz do
 * projeto — ver .env.example para a lista de chaves esperadas). Nunca
 * ficam hardcoded neste arquivo.
 */

require_once __DIR__ . '/env.php';

$__vendorAutoload = dirname(__DIR__) . '/vendor/autoload.php';
if (is_file($__vendorAutoload)) {
    require_once $__vendorAutoload;
}
unset($__vendorAutoload);

if (!function_exists('enviarCodigoVerificacao')) {

    function enviarCodigoVerificacao(string $email, string $nome, string $codigo): bool {

        if (!class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) {
            error_log('[mailer.php] PHPMailer não encontrado em vendor/. Rode "composer install".');
            return false;
        }

        $host      = getenv('SMTP_HOST') ?: '';
        $port      = (int) (getenv('SMTP_PORT') ?: 587);
        $usuario   = getenv('SMTP_USER') ?: '';
        $senha     = getenv('SMTP_PASS') ?: '';
        $seguranca = getenv('SMTP_SECURE') ?: 'tls';
        $deEmail   = getenv('SMTP_FROM_EMAIL') ?: $usuario;
        $deNome    = getenv('SMTP_FROM_NAME') ?: 'JovemConnect';

        if ($host === '' || $usuario === '' || $senha === '') {
            // Não expõe qual credencial falta em detalhe — só que a configuração está ausente.
            error_log('[mailer.php] Configuração de SMTP ausente ou incompleta (SMTP_HOST/SMTP_USER/SMTP_PASS no .env).');
            return false;
        }

        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host       = $host;
            $mail->SMTPAuth   = true;
            $mail->Username   = $usuario;
            $mail->Password   = $senha;
            if ($seguranca === 'ssl') {
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            } elseif ($seguranca === 'none') {
                // Só para relays locais/confiáveis sem TLS (ex.: ambiente de teste).
                // Provedores reais de e-mail exigem "tls" ou "ssl" — ver .env.example.
                $mail->SMTPSecure  = '';
                $mail->SMTPAutoTLS = false;
            } else {
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            }
            $mail->Port       = $port;
            $mail->CharSet    = 'UTF-8';
            $mail->Timeout    = 10;

            $mail->setFrom($deEmail, $deNome);
            $mail->addAddress($email, $nome);

            $mail->isHTML(true);
            $mail->Subject = 'Seu código de verificação — JovemConnect';
            $mail->Body =
                '<div style="font-family:Arial,sans-serif;max-width:480px;margin:0 auto;">' .
                '<p>Olá, ' . htmlspecialchars($nome) . '!</p>' .
                '<p>Use o código abaixo para concluir a verificação da sua conta no JovemConnect:</p>' .
                '<p style="font-size:28px;font-weight:bold;letter-spacing:6px;text-align:center;' .
                'background:#f1f5f9;padding:16px;border-radius:8px;">' . htmlspecialchars($codigo) . '</p>' .
                '<p>Esse código expira em 15 minutos.</p>' .
                '<p style="color:#71717a;font-size:12px;">Se você não solicitou isso, ignore este e-mail.</p>' .
                '</div>';
            $mail->AltBody = "Seu código de verificação JovemConnect: {$codigo} (expira em 15 minutos). "
                . "Se você não solicitou isso, ignore este e-mail.";

            $mail->send();
            return true;

        } catch (\PHPMailer\PHPMailer\Exception $e) {
            // ErrorInfo é a mensagem de protocolo do PHPMailer/SMTP — nunca inclui
            // a senha configurada nem o código de verificação enviado.
            error_log('[mailer.php] Falha ao enviar e-mail: ' . $mail->ErrorInfo);
            return false;
        }
    }
}
