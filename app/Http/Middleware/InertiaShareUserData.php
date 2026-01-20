<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class InertiaShareUserData
{
    public function handle(Request $request, Closure $next)
    {
        if (Auth::check()) {
            Inertia::share([
                'auth' => [
                    'user' => Auth::user()->shareToInertia(),
                ],
                'flash' => [
                    'success' => fn () => $request->session()->get('success'),
                    'error' => fn () => $request->session()->get('error'),
                    'warning' => fn () => $request->session()->get('warning'),
                    'info' => fn () => $request->session()->get('info'),
                ],
            ]);
        }

        return $next($request);
    }
}