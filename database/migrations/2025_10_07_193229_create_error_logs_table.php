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
        Schema::create('error_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversion_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            $table->enum('error_type', ['syntax', 'semantic', 'compilation', 'runtime', 'rdp_parsing']);
            $table->text('error_message');
            $table->json('error_location')->nullable(); // Line, column, file info
            $table->json('rdp_analysis')->nullable(); // RDP-specific error analysis
            $table->json('suggested_fixes')->nullable(); // RDP-generated suggestions
            $table->enum('severity', ['error', 'warning', 'info'])->default('error');
            $table->string('error_code')->nullable(); // Error code for categorization
            $table->json('context')->nullable(); // Additional context information
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['conversion_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['error_type', 'severity']);
            $table->index('error_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('error_logs');
    }
};