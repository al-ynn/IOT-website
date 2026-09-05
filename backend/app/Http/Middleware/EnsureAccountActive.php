<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureAccountActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless($user?->isActive(), 403, 'Account is inactive.');
        abort_if($user->organization && $user->organization->status !== 'active', 403, 'Organization is inactive.');

        return $next($request);
    }
}
