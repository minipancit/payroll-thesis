<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class User extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'password',
        'employee_id',
        'employee_type',
        'department',
        'position',
        'hire_date',
        'birth_date',
        'gender',
        'marital_status',
        'dependents',
        'phone',
        'address',
        'city',
        'state',
        'zip_code',
        'country',
        'bank_name',
        'bank_account_number',
        'bank_account_name',
        'bank_routing_number',
        'tin',
        'sss',
        'philhealth',
        'pagibig',
        'basic_salary',
        'daily_rate',
        'hourly_rate',
        'pay_frequency',
        'next_pay_date',
        'sick_leave_balance',
        'vacation_leave_balance',
        'emergency_leave_balance',
        'default_shift_start',
        'default_shift_end',
        'grace_period_minutes',
        'is_admin',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'bank_account_number',
        'bank_routing_number',
        'tin',
        'sss',
        'philhealth',
        'pagibig',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'hire_date' => 'date',
        'birth_date' => 'date',
        'next_pay_date' => 'date',
        'default_shift_start' => 'datetime',
        'default_shift_end' => 'datetime',
        'basic_salary' => 'decimal:2',
        'daily_rate' => 'decimal:2',
        'hourly_rate' => 'decimal:2',
        'is_admin' => 'boolean',
        'is_active' => 'boolean',
        'dependents' => 'integer',
        'sick_leave_balance' => 'integer',
        'vacation_leave_balance' => 'integer',
        'emergency_leave_balance' => 'integer',
        'grace_period_minutes' => 'integer',
    ];

    // Relationships
    public function timeLogs(): HasMany
    {
        return $this->hasMany(TimeLog::class);
    }

    public function dailyTimeRecords(): HasMany
    {
        return $this->hasMany(DailyTimeRecord::class);
    }

    public function payslips(): HasMany
    {
        return $this->hasMany(Payslip::class);
    }

    // Accessors for Inertia
    public function getRoleAttribute(): string
    {
        return $this->is_admin ? 'admin' : 'employee';
    }

    public function getPermissionsAttribute(): array
    {
        return $this->is_admin 
            ? ['*'] // Admin has all permissions
            : ['view_own_payslip', 'time_in_out', 'view_own_attendance', 'view_own_dtr'];
    }

    public function getFormattedSalaryAttribute(): string
    {
        return '₱' . number_format($this->basic_salary, 2);
    }

    public function getFullAddressAttribute(): string
    {
        $parts = [];
        if ($this->address) $parts[] = $this->address;
        if ($this->city) $parts[] = $this->city;
        if ($this->state) $parts[] = $this->state;
        if ($this->zip_code) $parts[] = $this->zip_code;
        if ($this->country) $parts[] = $this->country;
        
        return implode(', ', $parts);
    }

    public function getAgeAttribute(): ?int
    {
        return $this->birth_date ? Carbon::parse($this->birth_date)->age : null;
    }

    public function getYearsOfServiceAttribute(): ?int
    {
        return $this->hire_date ? Carbon::parse($this->hire_date)->diffInYears() : null;
    }

    // Permission check for Inertia - RENAMED FROM can() to hasPermission()
    public function hasPermission($permission): bool
    {
        if ($this->is_admin) return true;
        
        $permissions = $this->permissions;
        return in_array($permission, $permissions);
    }

    // Salary calculations
    public function calculateDailyRate(): float
    {
        return $this->daily_rate ?? round($this->basic_salary / 22, 2);
    }

    public function calculateHourlyRate(): float
    {
        return $this->hourly_rate ?? round($this->calculateDailyRate() / 8, 2);
    }

    public function calculateOvertimeRate(): float
    {
        return round($this->calculateHourlyRate() * 1.25, 2);
    }

    // For Inertia sharing
    public function shareToInertia(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'employee_id' => $this->employee_id,
            'department' => $this->department,
            'position' => $this->position,
            'role' => $this->role,
            'permissions' => $this->permissions,
            'is_admin' => $this->is_admin,
            'is_active' => $this->is_active,
            'avatar' => $this->getAvatarUrl(),
        ];
    }

    protected function getAvatarUrl(): string
    {
        $hash = md5(strtolower(trim($this->email)));
        return "https://www.gravatar.com/avatar/{$hash}?d=identicon";
    }

    // Scopes
    public function scopeAdmin($query)
    {
        return $query->where('is_admin', true);
    }

    public function scopeEmployee($query)
    {
        return $query->where('is_admin', false);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByDepartment($query, $department)
    {
        return $query->where('department', $department);
    }
}