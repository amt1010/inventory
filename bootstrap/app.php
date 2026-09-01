<?php

use App\Exceptions\ClerkVerificationException;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Railway (like most PaaS) terminates TLS at its edge and forwards
        // plain HTTP to the container, setting X-Forwarded-Proto. Without
        // trusting that proxy, Laravel thinks every request is HTTP, so
        // asset()/url() generate http:// links even on an https:// site.
        // The edge's IP isn't fixed, and the container has no other public
        // ingress, so trusting all proxies here is safe.
        $middleware->trustProxies(at: '*');

        $middleware->append(SecurityHeaders::class);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (ClerkVerificationException $e, Request $request) {
            return response()->json(['error' => 'Google sign-in failed. Please try again.'], 422);
        });
    })->create();
