<?php

namespace App\Console\Commands;

use App\Services\DiscordNotifier;
use Illuminate\Console\Command;

class TestDiscordWebhook extends Command
{
    protected $signature = 'discord:test';

    protected $description = 'Envía un mensaje de prueba al webhook de Discord';

    public function handle(): int
    {
        if (! config('services.discord.webhook_url')) {
            $this->error('DISCORD_WEBHOOK_URL no está configurado.');

            return self::FAILURE;
        }

        $this->info('Enviando mensaje de prueba a Discord...');

        $sent = (new DiscordNotifier())->notify(
            sprintf('[%s] Prueba de webhook', config('app.env')),
            sprintf(
                "**App:** %s\n**URL:** %s\n**Estado:** conexión OK desde el servidor",
                config('app.name'),
                config('app.url'),
            ),
            0x5865F2,
        );

        if (! $sent) {
            $this->error('No se pudo enviar. Revisá storage/logs/laravel.log');

            return self::FAILURE;
        }

        $this->info('Mensaje enviado correctamente.');

        return self::SUCCESS;
    }
}
