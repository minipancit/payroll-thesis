<?php

namespace App\Http\Controllers;

use App\Models\DailyTimeRecord;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DTRController extends Controller
{
    public function index(){
        return Inertia::render('Dashboard');
    }

    public function update(Request $request, DailyTimeRecord $dtr){

        return $dtr;    
    }
}
