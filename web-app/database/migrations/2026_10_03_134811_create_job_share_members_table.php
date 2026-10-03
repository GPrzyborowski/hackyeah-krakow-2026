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
        Schema::create('job_share_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_share_pair_id')->constrained()->cascadeOnDelete();
            $table->foreignId('candidate_profile_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_initiator')->default(false);
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('schedule_confirmed_at')->nullable();
            $table->timestamps();

            $table->unique(['job_share_pair_id', 'candidate_profile_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('job_share_members');
    }
};
