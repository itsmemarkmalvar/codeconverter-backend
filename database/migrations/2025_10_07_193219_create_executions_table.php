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
        Schema::create('executions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversion_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            $table->enum('language', ['javascript', 'csharp']);
            $table->longText('code');
            $table->longText('execution_output')->nullable();
            $table->json('compilation_errors')->nullable();
            $table->json('runtime_errors')->nullable();
            $table->integer('execution_time_ms')->nullable();
            $table->integer('compilation_time_ms')->nullable();
            $table->integer('memory_usage_kb')->nullable();
            $table->boolean('success')->default(false);
            $table->integer('exit_code')->nullable();
            $table->json('performance_metrics')->nullable(); // Additional performance data
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['conversion_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['language', 'success']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('executions');
    }
};