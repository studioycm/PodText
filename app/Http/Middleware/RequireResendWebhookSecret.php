<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RequireResendWebhookSecret
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $secret = config('resend.webhook.secret');

        if (! is_string($secret) || $secret === '0' || trim($secret) === '') {
            return new Response(status: Response::HTTP_SERVICE_UNAVAILABLE);
        }

        return $next($request);
    }
}
