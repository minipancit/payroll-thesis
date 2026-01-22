<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PayrollController extends Controller
{
    public function index(Request $request){
        $users = User::query()
            ->orderBy('created_at','desc')
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%");
                    $q->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->paginate(10)
            ->withQueryString();
        

        return Inertia::render('Admin/Payroll/Index', [
            'modules' => $users,
            'filters' => $request->only('search'),
        ]);
    }
    public function process(){
        return Inertia::render('Dashboard');
    }


}
