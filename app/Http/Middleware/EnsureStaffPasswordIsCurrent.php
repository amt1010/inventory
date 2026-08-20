<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStaffPasswordIsCurrent
{
    public function handle(Request $request, Closure $next): Response
    {
        $staff = $request->user('staff');

        if ($staff?->must_change_password && ! $request->routeIs('admin.change-password')) {
            return redirect()->route('admin.change-password');
        }

        return $next($request);
    }
}
