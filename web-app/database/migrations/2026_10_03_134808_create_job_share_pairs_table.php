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
        Schema::create('job_share_pairs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_offer_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('forming')->index();
            $table->json('proposed_schedule')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('job_share_pairs');
    }
};
