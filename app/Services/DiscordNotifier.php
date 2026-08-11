<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DiscordNotifier
{
    /**
     * Envía un embed al webhook de Discord configurado en services.discord.webhook_url.
     */
    public function notify(string $title, string $description, int $color = 0x5865F2): bool
    {
        $webhookUrl = config('services.discord.webhook_url');

        if (! $webhookUrl) {
            Log::warning('DiscordNotifier: DISCORD_WEBHOOK_URL no configurado.');

            return false;
        }

        try {
            $response = Http::timeout(10)->post($webhookUrl, [
                'embeds' => [[
                    'title' => $title,
                    'description' => $description,
                    'color' => $color,
                ]],
            ]);

            if ($response->failed()) {
                Log::error('DiscordNotifier: webhook respondió con error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return false;
            }

            return true;
        } catch (\Throwable $exception) {
            Log::error('DiscordNotifier: no se pudo enviar la notificación', [
                'message' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    public function notifyOpenAiUsage(string $context, int $promptTokens, int $completionTokens, float $estimatedCost): void
    {
        $this->notify(
            'Uso de OpenAI',
            sprintf(
                "**Contexto:** %s\n**Tokens de entrada:** %s\n**Tokens de salida:** %s\n**Costo estimado (entrada):** USD %s",
                $context,
                number_format($promptTokens),
                number_format($completionTokens),
                number_format($estimatedCost, 4)
            ),
            0x10A37F
        );
    }

    public function notifyHttpError(int $status, string $url, string $method, ?string $user = null): void
    {
        $description = sprintf(
            "**HTTP:** %s\n**URL:** %s\n**Método:** %s\n**Usuario:** %s",
            $status,
            $url,
            $method,
            $user ?? '—',
        );

        $this->notify(
            sprintf('[%s] Error HTTP %s', config('app.env'), $status),
            $description,
            0xED4245,
        );
    }

    public function notifyException(\Throwable $exception): void
    {
        $request = request();

        $description = sprintf(
            "**Mensaje:** %s\n**Archivo:** %s:%s\n**URL:** %s\n**Método:** %s",
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine(),
            $request?->fullUrl() ?? '—',
            $request?->method() ?? '—',
        );

        if ($exception instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) {
            $description .= sprintf("\n**HTTP:** %s", $exception->getStatusCode());
        }

        $this->notify(
            sprintf('[%s] %s', config('app.env'), class_basename($exception)),
            $description,
            0xED4245,
        );
    }
}
