<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('login_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->string('face_data_hash')->nullable();
            $table->string('status'); // pending, success, failed
            $table->string('failure_reason')->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->json('device_info')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->float('confidence_score')->nullable();
            $table->timestamp('attempted_at')->nullable();
            $table->timestamp('authenticated_at')->nullable();
            $table->timestamps();
            
            $table->index(['user_id', 'status', 'attempted_at']);
            $table->index(['face_data_hash', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_attempts');
    }
};