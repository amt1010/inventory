<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * Filament (admin/seller) ships its own JS/CSS locally (public/js|css/filament/*),
     * so 'self' covers both panels — only the public catalog layout pulls from a CDN.
     * ui-avatars.com is Filament's default AvatarProvider (no custom one is
     * registered) — every staff/seller user menu avatar loads from there.
     */
    private const CSP = "default-src 'self'; "
        ."script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://www.google.com/recaptcha/ https://www.gstatic.com/recaptcha/; "
        ."style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com; "
        ."font-src 'self' https://fonts.gstatic.com data:; "
        ."img-src 'self' data: blob: https://ui-avatars.com; "
        ."connect-src 'self'; "
        ."frame-src 'self' https://www.google.com/recaptcha/ https://recaptcha.google.com/recaptcha/; "
        ."frame-ancestors 'self'; "
        ."object-src 'none'; "
        ."base-uri 'self'; "
        ."form-action 'self'";

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Content-Security-Policy', self::CSP);

        if ($request->secure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
