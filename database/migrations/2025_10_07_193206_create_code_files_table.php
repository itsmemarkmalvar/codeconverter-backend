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
        Schema::create('code_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('filename');
            $table->enum('file_type', ['javascript', 'csharp']);
            $table->longText('file_content');
            $table->integer('file_size'); // File size in bytes
            $table->string('file_hash')->nullable(); // Hash for duplicate detection
            $table->json('metadata')->nullable(); // Additional file metadata
            $table->boolean('is_public')->default(false); // Public/private file
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['user_id', 'created_at']);
            $table->index(['file_type', 'is_public']);
            $table->index('file_hash');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('code_files');
    }
};