<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PayrollAccess
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();
        
        if (!$user || !$user->canAccessPayrollModule()) {
            return redirect()->route('dashboard')->with('error', 'You do not have access to the payroll module.');
        }
        
        return $next($request);
    }
}