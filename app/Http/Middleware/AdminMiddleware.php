<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!auth()->check() || auth()->user()->role !== 'admin') {

            auth()->logout();

            return redirect()->route('login')
                ->with('error', 'Only administrators can access this panel.');
        }

        return $next($request);
    }
}
