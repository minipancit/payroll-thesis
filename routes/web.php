<?php

use App\Http\Controllers\DTRController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PayrollController;
use App\Http\Controllers\PayrollRegisterController;
use App\Http\Controllers\TimeLogController;
use App\Http\Controllers\UserController;
use App\Http\Middleware\AdminOnly;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware([
    'auth'
])->group(function(){

    Route::get('/',[HomeController::class,'index'])->name('home');

    Route::post('/time-in/{event}',[TimeLogController::class,'timeIn'])->name('timeIn');
    Route::post('/time-out/{event}',[TimeLogController::class,'timeOut'])->name('timeOut');


    Route::get('/payslip', function(){
        return Inertia::render('Welcome');
    })->name('myPayslips');

    Route::get('/my-dtr', function(){
        return Inertia::render('Welcome');
    })->name('myDtr');




    Route::middleware(AdminOnly::class)
        ->prefix('admin')
        ->name('admin.')
        ->group(function(){

            Route::get('dashboard', function () {
                return Inertia::render('Dashboard');
            })->name('dashboard');


            Route::get('/dtr',[DTRController::class,'index'])->name('dtr.index');
            Route::get('/payroll',[PayrollController::class,'index'])->name('payroll.index');
            Route::post('/payroll',[PayrollController::class,'proccess'])->name('payroll.process');
            Route::get('/payroll-register',[PayrollRegisterController::class,'index'])->name('payroll.payroll-register.index');
            Route::get('/payroll-register/{register}',[PayrollRegisterController::class,'register'])->name('payroll.payroll-register.item');
            Route::resource('event',EventController::class);
            Route::resource('user',UserController::class);

        });
});

require __DIR__.'/settings.php';
