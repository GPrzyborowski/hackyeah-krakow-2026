<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Log of texts blocked by the message moderator (the blocked text itself is never stored as content).
     */
    public function up(): void
    {
        Schema::create('moderation_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->string('context', 40);
            $table->nullableMorphs('subject');
            $table->string('excerpt', 300)->nullable();
            $table->text('reason')->nullable();
            $table->text('suggestion')->nullable();
            $table->string('moderator', 20);
            $table->timestamp('created_at')->useCurrent()->index();

            $table->index(['company_id', 'created_at']);
            $table->index(['context', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('moderation_events');
    }
};
