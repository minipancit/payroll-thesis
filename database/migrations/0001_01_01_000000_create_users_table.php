<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            
            // Employee Information
            $table->string('employee_id')->unique()->nullable();
            $table->string('employee_type')->default('regular'); // regular, probationary, contractual
            $table->string('department')->nullable();
            $table->string('position')->nullable();
            $table->date('hire_date')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('gender')->nullable();
            $table->string('marital_status')->nullable();
            $table->integer('dependents')->default(0);
            
            // Contact Information
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('zip_code')->nullable();
            $table->string('country')->nullable();
            
            // Bank Information
            $table->string('bank_name')->nullable();
            $table->string('bank_account_number')->nullable();
            $table->string('bank_account_name')->nullable();
            $table->string('bank_routing_number')->nullable();
            
            // Government IDs (Philippines)
            $table->string('tin')->nullable();
            $table->string('sss')->nullable();
            $table->string('philhealth')->nullable();
            $table->string('pagibig')->nullable();
            
            // Salary Information
            $table->decimal('basic_salary', 12, 2)->default(0);
            $table->decimal('daily_rate', 10, 2)->nullable();
            $table->decimal('hourly_rate', 10, 2)->nullable();
            $table->string('pay_frequency')->default('semi-monthly');
            $table->date('next_pay_date')->nullable();
            
            // Leave Information
            $table->integer('sick_leave_balance')->default(0);
            $table->integer('vacation_leave_balance')->default(0);
            $table->integer('emergency_leave_balance')->default(0);
            
            // Attendance Settings
            $table->time('default_shift_start')->nullable();
            $table->time('default_shift_end')->nullable();
            $table->integer('grace_period_minutes')->default(15);
            
            // Role - Just admin or regular user
            $table->boolean('is_admin')->default(false);
            
            // Account Status
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->rememberToken();
            $table->timestamps();
            
            // Indexes
            $table->index(['employee_id', 'is_active']);
            $table->index(['department', 'position']);
            $table->index('is_admin');
            $table->index('hire_date');
            $table->index('next_pay_date');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
