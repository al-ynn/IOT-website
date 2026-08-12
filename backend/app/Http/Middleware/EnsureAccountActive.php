<?php
namespace App\Http\Middleware;
use Closure;use Illuminate\Http\Request;use Symfony\Component\HttpFoundation\Response;
class EnsureAccountActive{public function handle(Request $r,Closure $next):Response{abort_if($r->user()?->status==='suspended'||$r->user()?->organization?->status==='suspended',403,'Account is suspended.');return $next($r);}}
