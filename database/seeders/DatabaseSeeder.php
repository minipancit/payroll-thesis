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
                'daily_rate' => 909.09,
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
                'daily_rate' => 909.09,
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
                'name' => 'Wedding Photography – Mark & Anna',
                'address' => 'San Agustin Church, Intramuros, Manila',
                'event_date' => Carbon::now()->toDateString(),
                'start_time' => '13:00:00',
                'end_time' => '18:00:00',
                'latitude' => 14.5896,
                'longitude' => 120.9747,
                'description' => 'Full wedding photo coverage including ceremony and reception.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => '18th Birthday Photoshoot – Sofia',
                'address' => 'The Peninsula Manila, Makati City',
                'event_date' => Carbon::now()->addDays(7)->toDateString(),
                'start_time' => '15:00:00',
                'end_time' => '20:00:00',
                'latitude' => 14.5537,
                'longitude' => 121.0246,
                'description' => 'Debut birthday photography with family and friends.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Christening Photography – Baby Lucas',
                'address' => 'St. Andrew the Apostle Church, Parañaque',
                'event_date' => Carbon::now()->addDays(4)->toDateString(),
                'start_time' => '09:00:00',
                'end_time' => '12:00:00',
                'latitude' => 14.4805,
                'longitude' => 121.0193,
                'description' => 'Christening event photography coverage.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Corporate Event Photography – Annual Gala',
                'address' => 'SMX Convention Center, Pasay City',
                'event_date' => Carbon::now()->addDays(21)->toDateString(),
                'start_time' => '18:00:00',
                'end_time' => '22:00:00',
                'latitude' => 14.5362,
                'longitude' => 120.9822,
                'description' => 'Corporate gala night photography coverage.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Birthday Party Photography – 1st Birthday',
                'address' => 'Private Residence, Quezon City',
                'event_date' => Carbon::now()->addDays(2)->toDateString(),
                'start_time' => '10:00:00',
                'end_time' => '14:00:00',
                'latitude' => 14.6760,
                'longitude' => 121.0437,
                'description' => 'Kids birthday party candid and themed photos.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
