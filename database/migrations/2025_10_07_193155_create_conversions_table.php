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
        Schema::create('conversions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            $table->enum('source_language', ['javascript', 'csharp']);
            $table->enum('target_language', ['javascript', 'csharp']);
            $table->longText('source_code');
            $table->longText('converted_code')->nullable();
            $table->enum('conversion_status', ['success', 'error', 'warning'])->default('success');
            $table->json('rdp_metrics')->nullable(); // RDP performance metrics
            $table->json('syntax_errors')->nullable(); // RDP syntax errors
            $table->json('semantic_analysis')->nullable(); // RDP semantic analysis
            $table->integer('rdp_parsing_time_ms')->nullable(); // RDP parsing time
            $table->integer('conversion_time_ms')->nullable(); // Total conversion time
            $table->integer('ast_nodes')->nullable(); // Number of AST nodes generated
            $table->integer('tokens_processed')->nullable(); // Number of tokens processed
            $table->integer('memory_usage_kb')->nullable(); // Memory usage during parsing
            $table->integer('error_recovery_count')->nullable(); // RDP error recovery attempts
            $table->decimal('syntax_accuracy', 5, 2)->nullable(); // Syntax accuracy percentage
            $table->decimal('semantic_preservation', 5, 2)->nullable(); // Semantic preservation percentage
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['user_id', 'created_at']);
            $table->index(['source_language', 'target_language']);
            $table->index('conversion_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conversions');
    }
};