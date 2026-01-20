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
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('address');
            $table->date('event_date'); // Date of the event
            $table->time('start_time')->nullable(); // Event start time
            $table->time('end_time')->nullable(); // Event end time
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->text('description')->nullable(); // Optional description
            $table->timestamps();
            
            // Indexes for better query performance
            $table->index(['event_date', 'start_time']);
            $table->index(['event_date', 'end_time']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
