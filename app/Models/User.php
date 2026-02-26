<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Laravel\Sanctum\HasApiTokens;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes, TwoFactorAuthenticatable;

    protected $fillable = [
        'name',
        'first_name',
        'last_name',
        'middle_name',
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
        'face_data_hash',
        'face_registered_at',
        'face_updated_at',
        'last_login_at',
        'last_login_ip',
        'last_login_location',
        'is_admin',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
        'bank_account_number',
        'bank_routing_number',
        'tin',
        'sss',
        'philhealth',
        'pagibig',
        'face_data_hash',
    ];

    protected $casts = [
        'facial_images' => 'array',
        'face_trained_at' => 'datetime',
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'hire_date' => 'date',
        'birth_date' => 'date',
        'next_pay_date' => 'date',
        'default_shift_start' => 'datetime:H:i',
        'default_shift_end' => 'datetime:H:i',
        'face_registered_at' => 'datetime',
        'face_updated_at' => 'datetime',
        'last_login_at' => 'datetime',
        'last_login_location' => 'array',
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

    protected $appends = [
        'formatted_hire_date',
        'role',
        'permissions',
        'formatted_salary',
        'full_address',
        'age',
        'years_of_service',
        'avatar_url',
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

    // public function payslips(): HasMany
    // {
    //     return $this->hasMany(Payslip::class);
    // }

    public function faceImages(): HasMany {
        return $this->hasMany(UserFaceImage::class);
    }

    public function loginAttempts(): HasMany
    {
        return $this->hasMany(LoginAttempt::class);
    }

    // Accessors
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

    public function getFormattedHireDateAttribute(): string
    {
        return $this->hire_date ? Carbon::parse($this->hire_date)->format('F j, Y') : '';
    }

    public function getAvatarUrlAttribute(): string
    {
        $hash = md5(strtolower(trim($this->email)));
        return "https://www.gravatar.com/avatar/{$hash}?d=identicon";
    }

    // Face recognition methods
    public function hasFaceRegistered(): bool
    {
        return !empty($this->face_data_hash);
    }

    public function registerFaceData(string $faceData): void
    {
        $this->update([
            'face_data_hash' => hash('sha256', $faceData),
            'face_registered_at' => now(),
        ]);
    }

    public function updateFaceData(string $oldFaceData, string $newFaceData): bool
    {
        // Verify old face data first
        if (hash('sha256', $oldFaceData) !== $this->face_data_hash) {
            return false;
        }

        $this->update([
            'face_data_hash' => hash('sha256', $newFaceData),
            'face_updated_at' => now(),
        ]);

        return true;
    }

    public function verifyFaceData(string $faceData): bool
    {
        return hash('sha256', $faceData) === $this->face_data_hash;
    }

    public function removeFaceData(): void
    {
        $this->update([
            'face_data_hash' => null,
            'face_registered_at' => null,
            'face_updated_at' => null,
        ]);
    }

    public function recordLogin(string $ipAddress, array|null $location = null): void
    {
        $this->update([
            'last_login_at' => now(),
            'last_login_ip' => $ipAddress,
            'last_login_location' => $location ? json_encode($location) : null,
        ]);
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

    // Token methods
    public function createFaceRecognitionToken()
    {
        return $this->createToken(
            'face-recognition-token',
            ['*'],
            now()->addDays(30) // Token expires in 30 days
        )->plainTextToken;
    }

    public function getValidTokens()
    {
        return $this->tokens()
            ->where('expires_at', '>', now())
            ->orWhereNull('expires_at')
            ->get();
    }

    public function revokeAllTokens(): void
    {
        $this->tokens()->delete();
    }

    public function revokeOtherTokens($currentTokenId): void
    {
        $this->tokens()
            ->where('id', '!=', $currentTokenId)
            ->delete();
    }

    // For Inertia sharing
    public function shareToInertia(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'employee_id' => $this->employee_id,
            'department' => $this->department,
            'position' => $this->position,
            'role' => $this->role,
            'permissions' => $this->permissions,
            'is_admin' => $this->is_admin,
            'is_active' => $this->is_active,
            'avatar_url' => $this->avatar_url,
            'has_face_registered' => $this->hasFaceRegistered(),
        ];
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

    public function scopeHasFaceRegistered($query)
    {
        return $query->whereNotNull('face_data_hash');
    }

    public function scopeNoFaceRegistered($query)
    {
        return $query->whereNull('face_data_hash');
    }

    // Static methods for face recognition
    public static function findByFaceData(string $faceData)
    {
        $faceHash = hash('sha256', $faceData);
        
        return static::where('face_data_hash', $faceHash)
            ->where('is_active', true)
            ->first();
    }

    // Business logic methods
    public function isOnProbation(): bool
    {
        return $this->employee_type === 'probationary' && 
               $this->hire_date && 
               Carbon::parse($this->hire_date)->diffInMonths() < 6;
    }

    public function getLeaveBalance(string $type): int
    {
        return match($type) {
            'sick' => $this->sick_leave_balance,
            'vacation' => $this->vacation_leave_balance,
            'emergency' => $this->emergency_leave_balance,
            default => 0,
        };
    }

    public function deductLeave(string $type, int $days = 1): bool
    {
        $currentBalance = $this->getLeaveBalance($type);
        
        if ($currentBalance >= $days) {
            $field = $type . '_leave_balance';
            $this->$field = $currentBalance - $days;
            $this->save();
            return true;
        }
        
        return false;
    }

    public function addLeave(string $type, int $days = 1): void
    {
        $field = $type . '_leave_balance';
        $this->$field = ($this->$field ?? 0) + $days;
        $this->save();
    }

    // Security methods
    public function hasRecentFailedLoginAttempts(int $minutes = 15, int $maxAttempts = 5): bool
    {
        return $this->loginAttempts()
            ->where('status', 'failed')
            ->where('attempted_at', '>', now()->subMinutes($minutes))
            ->count() >= $maxAttempts;
    }

    public function clearFailedLoginAttempts(): void
    {
        $this->loginAttempts()
            ->where('status', 'failed')
            ->delete();
    }

    public function faceEmbeddings(): HasMany
    {
        return $this->hasMany(UserFaceEmbedding::class);
    }

    public function primaryFaceEmbedding(): HasOne
    {
        return $this->hasOne(UserFaceEmbedding::class)->where('is_primary', true);
    }

    public function addFaceEmbedding(array $embedding, string|null $imagePath = null): UserFaceEmbedding
    {
        // Mark existing primary as non-primary
        $this->faceEmbeddings()->where('is_primary', true)->update(['is_primary' => false]);
        
        return $this->faceEmbeddings()->create([
            'embedding' => $embedding,
            'image_path' => $imagePath,
            'is_primary' => true,
            'metadata' => [
                'created_at' => now()->toISOString(),
                'dimensions' => count($embedding),
            ],
        ]);
    }

    public function getFaceEmbedding(): ?array
    {
        $embedding = $this->primaryFaceEmbedding;
        return $embedding ? $embedding->embedding : null;
    }


    public function scopeWithFaceEmbeddings($query)
    {
        return $query->whereHas('faceEmbeddings');
    }

    public function scopeWithoutFaceEmbeddings($query)
    {
        return $query->whereDoesntHave('faceEmbeddings');
    }
}