<?php

namespace App\Http\Middleware;

use App\Services\DiscordNotifier;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class NotifyHttpErrorsToDiscord
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! app()->environment('production')) {
            return $response;
        }

        $status = $response->getStatusCode();

        if (
            $status < 403
            || $status === 404
            || $request->is('up')
        ) {
            return $response;
        }

        $cacheKey = sprintf(
            'discord-http-error:%s:%s:%s',
            $status,
            $request->method(),
            sha1($request->path())
        );

        if (Cache::has($cacheKey)) {
            return $response;
        }

        Cache::put($cacheKey, true, now()->addMinutes(5));

        (new DiscordNotifier())->notifyHttpError(
            status: $status,
            url: $request->fullUrl(),
            method: $request->method(),
            user: $request->user()?->email,
        );

        return $response;
    }
}
