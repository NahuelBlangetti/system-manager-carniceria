<?php

use App\Http\Middleware\NotifyHttpErrorsToDiscord;
use App\Services\DiscordNotifier;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');
        $middleware->web(append: [
            NotifyHttpErrorsToDiscord::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $notifyDiscord = function (Throwable $exception): void {
            if (! app()->environment('production')) {
                return;
            }

            if (! config('services.discord.webhook_url')) {
                return;
            }

            (new DiscordNotifier())->notifyException($exception);
        };

        $exceptions->reportable(function (Throwable $exception) use ($notifyDiscord): void {
            if ($exception instanceof HttpExceptionInterface) {
                return;
            }

            if ($exception instanceof \Illuminate\Queue\MaxAttemptsExceededException) {
                return;
            }

            $notifyDiscord($exception);
        });
    })->create();
