<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, $role)
    {
        $user = Auth::user();
        
        if (!$user) {
            return redirect()->route('login');
        }
        
        switch ($role) {
            case 'admin':
                if (!$user->is_admin) {
                    return redirect()->route('dashboard')->with('error', 'Admin access required.');
                }
                break;
                
            case 'payroll_manager':
                if (!$user->is_payroll_manager && !$user->is_admin) {
                    return redirect()->route('dashboard')->with('error', 'Payroll manager access required.');
                }
                break;
                
            case 'hr_manager':
                if (!$user->is_hr_manager && !$user->is_admin) {
                    return redirect()->route('dashboard')->with('error', 'HR manager access required.');
                }
                break;
        }
        
        return $next($request);
    }
}