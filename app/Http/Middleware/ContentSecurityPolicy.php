<?php

namespace App\Http\Middleware;

use Closure;

class ContentSecurityPolicy
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $response = $next($request);

        // Menambahkan header Content Security Policy yang aman
        $csp = "default-src 'self'; ";
        $csp .= "frame-ancestors 'self'; ";
        $csp .= "form-action 'self'; ";
        $csp .= "script-src 'self'; ";
        $csp .= "style-src 'self';";

        $response->headers->set('Content-Security-Policy', $csp);

        return $response;
    }
}
