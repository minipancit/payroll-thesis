<?php

namespace Database\Seeders;

use App\Models\User;
use Carbon\Carbon;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::updateOrCreate(
            [
                'email' => 'hermogenessancio1234@gmail.com',
            ],
            [
                'name' => 'Hermogenes Sancio',
                'password' => Hash::make('password'), 
                'email_verified_at' => now(),

                'employee_id' => 'ADMIN-001',
                'employee_type' => 'regular',
                'department' => 'IT',
                'position' => 'Administrator',
                'hire_date' => now()->subYears(1),
                'gender' => 'Male',
                'marital_status' => 'Single',
                'dependents' => 0,

                // Contact Information
                'phone' => '09171234567',
                'address' => 'Manila, Philippines',
                'city' => 'Manila',
                'country' => 'PH',

                // Salary Information
                'basic_salary' => 50000,
                'daily_rate' => 800,
                'pay_frequency' => 'semi-monthly',

                // Leave Balances
                'sick_leave_balance' => 15,
                'vacation_leave_balance' => 15,
                'emergency_leave_balance' => 5,

                // Attendance Settings
                'default_shift_start' => '08:00:00',
                'default_shift_end' => '17:00:00',
                'grace_period_minutes' => 15,

                // Role & Status
                'is_admin' => true,
                'is_active' => true,
            ]
        );
        User::updateOrCreate(
            [
                'email' => 'admin@admin.com',
            ],
            [
                'name' => 'Hermogenes Sancio',
                'password' => Hash::make('password'), 
                'email_verified_at' => now(),

                'employee_id' => 'ADMIN-002',
                'employee_type' => 'regular',
                'department' => 'IT',
                'position' => 'Administrator',
                'hire_date' => now()->subYears(1),
                'gender' => 'Male',
                'marital_status' => 'Single',
                'dependents' => 0,

                // Contact Information
                'phone' => '09171234567',
                'address' => 'Manila, Philippines',
                'city' => 'Manila',
                'country' => 'PH',

                // Salary Information
                'basic_salary' => 50000,
                'daily_rate' => 800,
                'pay_frequency' => 'semi-monthly',

                // Leave Balances
                'sick_leave_balance' => 15,
                'vacation_leave_balance' => 15,
                'emergency_leave_balance' => 5,

                // Attendance Settings
                'default_shift_start' => '08:00:00',
                'default_shift_end' => '17:00:00',
                'grace_period_minutes' => 15,

                // Role & Status
                'is_admin' => true,
                'is_active' => true,
            ]
        );
        User::updateOrCreate(
            [
                'email' => 'user@gmail.com',
            ],
            [
                'name' => 'John Richmond Paguinto',
                'password' => Hash::make('password'), // change in production
                'email_verified_at' => now(),

                // Employee Information
                'employee_id' => 'EMP-001',
                'employee_type' => 'regular',
                'department' => 'Operations',
                'position' => 'Staff',
                'hire_date' => now()->subMonths(6),
                'birth_date' => '1995-05-10',
                'gender' => 'Female',
                'marital_status' => 'Single',
                'dependents' => 0,

                // Contact Information
                'phone' => '09181234567',
                'address' => 'Quezon City, Philippines',
                'city' => 'Quezon City',
                'country' => 'PH',

                // Government IDs (sample only)
                'tin' => '123-456-789-000',
                'sss' => '12-3456789-0',
                'philhealth' => '12-345678901-2',
                'pagibig' => '1234-5678-9012',

                // Salary Information
                'basic_salary' => 20000,
                'daily_rate' => 800,
                'hourly_rate' => 113.64,
                'pay_frequency' => 'semi-monthly',
                'next_pay_date' => now()->addDays(15),

                // Leave Balances
                'sick_leave_balance' => 10,
                'vacation_leave_balance' => 10,
                'emergency_leave_balance' => 3,

                // Attendance Settings
                'default_shift_start' => '08:00:00',
                'default_shift_end' => '17:00:00',
                'grace_period_minutes' => 15,

                // Role & Status
                'is_admin' => false,
                'is_active' => true,
            ]
        );
        User::updateOrCreate(
            [
                'email' => 'markgabrielbulahan@gmail.com',
            ],
            [
                'name' => 'Mark Gabriel Bulahan',
                'password' => Hash::make('password'), // change in production
                'email_verified_at' => now(),

                // Employee Information
                'employee_id' => 'EMP-002',
                'employee_type' => 'regular',
                'department' => 'Operations',
                'position' => 'Staff',
                'hire_date' => now()->subMonths(6),
                'birth_date' => '1995-05-10',
                'gender' => 'Female',
                'marital_status' => 'Single',
                'dependents' => 0,

                // Contact Information
                'phone' => '09181234567',
                'address' => 'Quezon City, Philippines',
                'city' => 'Quezon City',
                'country' => 'PH',

                // Government IDs (sample only)
                'tin' => '123-456-789-000',
                'sss' => '12-3456789-0',
                'philhealth' => '12-345678901-2',
                'pagibig' => '1234-5678-9012',

                // Salary Information
                'basic_salary' => 20000,
                'daily_rate' => 800,
                'hourly_rate' => 113.64,
                'pay_frequency' => 'semi-monthly',
                'next_pay_date' => now()->addDays(15),

                // Leave Balances
                'sick_leave_balance' => 10,
                'vacation_leave_balance' => 10,
                'emergency_leave_balance' => 3,

                // Attendance Settings
                'default_shift_start' => '08:00:00',
                'default_shift_end' => '17:00:00',
                'grace_period_minutes' => 15,

                // Role & Status
                'is_admin' => false,
                'is_active' => true,
            ]
        );

         DB::table('events')->insert([
            [
                'name' => 'Test',
                'address' => 'Doctor Arcadio Santos Avenue, corner Carlos P. Garcia Ave Ext, Parañaque, 1700 Metro Manila',
                'event_date' => "2026-01-21",
                'start_time' => '23:00:00',
                'end_time' => '23:59:00',
                'latitude' => 14.5636541,
                'longitude' => 121.0676173,
                'description' => 'Smart Blits Event',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Smart Blitz',
                'address' => 'Doctor Arcadio Santos Avenue, corner Carlos P. Garcia Ave Ext, Parañaque, 1700 Metro Manila',
                'event_date' => "2026-01-22",
                'start_time' => '7:00:00',
                'end_time' => '20:00:00',
                'latitude' => 14.3890472,
                'longitude' => 121.0251653,
                'description' => 'Smart Blits Event',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Smart Open House',
                'address' => 'Filinvest Corporate City, Commerce Ave, Muntinlupa, Metro Manila',
                'event_date' => "2026-01-23",
                'start_time' => '7:00:00',
                'end_time' => '20:00:00',
                'latitude' => 14.4166831,
                'longitude' => 121.041726,
                'description' => 'Smart Blits Event',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Smart Mini Open House',
                'address' => 'SM Center, Muntinlupa, 1774 Metro Manila',
                'event_date' => "2026-01-24",
                'start_time' => '7:00:00',
                'end_time' => '20:00:00',
                'latitude' => 14.377574,
                'longitude' => 121.0447665,
                'description' => 'Smart Blits Event',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
