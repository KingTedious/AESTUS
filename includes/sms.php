<?php
/**
 * includes/sms.php
 *
 * Envio de SMS via Twilio (SDK instalado via Composer — ver
 * composer.json / vendor/). Implementa a função enviarCodigoSMS(), já
 * usada por verificacoes/verificar_telefone.php, com a assinatura
 * descoberta nesse chamador:
 *
 *     enviarCodigoSMS(string $telefone, string $codigo): bool
 *
 * Credenciais do Twilio vêm de variáveis de ambiente (arquivo .env na
 * raiz do projeto — ver .env.example). Nunca ficam hardcoded aqui.
 */

require_once __DIR__ . '/env.php';

$__vendorAutoload = dirname(__DIR__) . '/vendor/autoload.php';
if (is_file($__vendorAutoload)) {
    require_once $__vendorAutoload;
}
unset($__vendorAutoload);

if (!function_exists('normalizarTelefoneE164')) {
    /**
     * Normaliza um telefone brasileiro (com ou sem máscara, com ou sem
     * código do país) para o formato E.164 exigido pela API do Twilio.
     * Retorna null se o número não tiver uma quantidade de dígitos
     * plausível para um celular/fixo brasileiro.
     */
    function normalizarTelefoneE164(string $telefone): ?string {
        $digitos = preg_replace('/\D/', '', $telefone);

        if ($digitos === null || $digitos === '') {
            return null;
        }

        // Já vem com código do país 55 + DDD (2) + número (8 ou 9) = 12 ou 13 dígitos.
        if (in_array(strlen($digitos), [12, 13], true) && str_starts_with($digitos, '55')) {
            return '+' . $digitos;
        }

        // DDD (2) + número (8 fixo ou 9 celular) = 10 ou 11 dígitos, sem o código do país.
        if (in_array(strlen($digitos), [10, 11], true)) {
            return '+55' . $digitos;
        }

        return null;
    }
}

if (!function_exists('enviarCodigoSMS')) {

    function enviarCodigoSMS(string $telefone, string $codigo): bool {

        if (!class_exists(\Twilio\Rest\Client::class)) {
            error_log('[sms.php] SDK do Twilio não encontrado em vendor/. Rode "composer install".');
            return false;
        }

        $sid      = getenv('TWILIO_SID') ?: '';
        $token    = getenv('TWILIO_AUTH_TOKEN') ?: '';
        $numeroDe = getenv('TWILIO_FROM_NUMBER') ?: '';

        if ($sid === '' || $token === '' || $numeroDe === '') {
            error_log('[sms.php] Configuração do Twilio ausente ou incompleta (TWILIO_SID/TWILIO_AUTH_TOKEN/TWILIO_FROM_NUMBER no .env).');
            return false;
        }

        $numeroPara = normalizarTelefoneE164($telefone);
        if ($numeroPara === null) {
            error_log('[sms.php] Número de telefone em formato não reconhecido para envio.');
            return false;
        }

        try {
            $client = new \Twilio\Rest\Client($sid, $token);
            $client->messages->create($numeroPara, [
                'from' => $numeroDe,
                'body' => "Seu código de verificação JovemConnect: {$codigo} (expira em 15 minutos).",
            ]);
            return true;

        } catch (\Twilio\Exceptions\TwilioException $e) {
            // getMessage() traz o erro retornado pela API do Twilio (ex.: número
            // inválido, credencial recusada) — nunca inclui o AUTH_TOKEN em si.
            error_log('[sms.php] Falha ao enviar SMS: ' . $e->getMessage());
            return false;
        }
    }
}
