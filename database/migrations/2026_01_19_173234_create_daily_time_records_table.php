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
        Schema::create('daily_time_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('event_id')->constrained()->onDelete('cascade');
            $table->date('log_date');
            $table->timestamp('scheduled_time_in')->nullable(); // Expected time in
            $table->timestamp('scheduled_time_out')->nullable(); // Expected time out
            $table->timestamp('actual_time_in')->nullable(); // First time-in of the day
            $table->timestamp('actual_time_out')->nullable(); // Last time-out of the day
            $table->integer('late_minutes')->default(0); // Minutes late
            $table->integer('overtime_minutes')->default(0); // Overtime minutes (>= 30 mins)
            $table->integer('undertime_minutes')->default(0); // Undertime minutes
            $table->decimal('total_hours', 5, 2)->nullable(); // Total hours worked
            $table->decimal('regular_hours', 5, 2)->nullable(); // Regular hours (8 hours)
            $table->decimal('overtime_hours', 5, 2)->nullable(); // Overtime hours (converted from minutes)
            $table->integer('time_log_count')->default(0);
            $table->json('time_logs_summary')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
            
            // Unique constraint
            $table->unique(['user_id', 'event_id', 'log_date']);
            
            // Indexes
            $table->index(['user_id', 'log_date']);
            $table->index(['event_id', 'log_date']);
            $table->index(['late_minutes']);
            $table->index(['overtime_minutes']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_time_records');
    }
};
