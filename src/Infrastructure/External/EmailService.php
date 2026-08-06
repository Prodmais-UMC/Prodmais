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
     * Envolve um trecho de conteúdo no template visual padrão do Prodmais
     * UMC (cabeçalho em gradiente + rodapé), pra todo e-mail transacional
     * ter a mesma identidade — recuperação de senha, aprovação de
     * cadastro, etc.
     */
    public function envelope(string $tituloTopo, string $corpoHtml): string
    {
        $ano = date('Y');
        return "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #1e40af, #3b82f6); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: #f8f9fa; padding: 30px; border-radius: 0 0 10px 10px; }
                .button { display: inline-block; padding: 15px 30px; background: #1e40af; color: white; text-decoration: none; border-radius: 8px; margin: 20px 0; }
                .footer { text-align: center; margin-top: 30px; color: #666; font-size: 12px; }
                .warning { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>{$tituloTopo}</h1>
                    <p>Sistema Prodmais UMC</p>
                </div>
                <div class='content'>
                    {$corpoHtml}
                </div>
                <div class='footer'>
                    <p>&copy; {$ano} Universidade de Mogi das Cruzes</p>
                    <p>Este e um email automatico, nao responda</p>
                </div>
            </div>
        </body>
        </html>
        ";
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
