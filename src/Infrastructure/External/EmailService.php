<?php

/**
 * Envio de e-mails transacionais via Resend (https://resend.com)
 */
class EmailService
{
    private ?string $apiKey;
    private string $remetentePadrao;

    public function __construct()
    {
        $this->apiKey = getenv('RESEND_API_KEY') ?: null;
        $this->remetentePadrao = getenv('RESEND_FROM') ?: 'Prodmais UMC <onboarding@resend.dev>';
    }

    public function isConfigured(): bool
    {
        return !empty($this->apiKey);
    }

    /**
     * Envia um e-mail HTML. Retorna true em caso de sucesso.
     */
    public function enviar(string $destinatario, string $assunto, string $htmlCorpo, ?string $remetente = null): bool
    {
        if (!$this->isConfigured()) {
            error_log('EmailService: RESEND_API_KEY não configurada — e-mail não enviado.');
            return false;
        }

        $payload = json_encode([
            'from' => $remetente ?? $this->remetentePadrao,
            'to' => [$destinatario],
            'subject' => $assunto,
            'html' => $htmlCorpo,
        ]);

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $this->apiKey,
                ],
                'content' => $payload,
                'timeout' => 15,
                'ignore_errors' => true,
            ],
        ]);

        $resposta = @file_get_contents('https://api.resend.com/emails', false, $context);

        if ($resposta === false) {
            error_log('EmailService: falha de rede ao contatar a API do Resend.');
            return false;
        }

        $statusLine = $http_response_header[0] ?? '';
        $sucesso = (bool) preg_match('/\s(2\d{2})\s/', $statusLine);

        if (!$sucesso) {
            error_log('EmailService: Resend retornou erro — ' . $statusLine . ' — ' . $resposta);
        }

        return $sucesso;
    }
}
