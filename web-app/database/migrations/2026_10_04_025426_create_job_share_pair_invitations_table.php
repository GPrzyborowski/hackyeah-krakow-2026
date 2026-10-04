<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Shareable links that let a friend of the initiator join her job-sharing pair.
     */
    public function up(): void
    {
        Schema::create('job_share_pair_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_share_pair_id')->constrained()->cascadeOnDelete();
            $table->string('token', 64)->unique();
            $table->foreignId('invited_by_candidate_profile_id')->constrained('candidate_profiles', indexName: 'jsp_invitations_invited_by_foreign')->cascadeOnDelete();
            $table->foreignId('accepted_by_candidate_profile_id')->nullable()->constrained('candidate_profiles', indexName: 'jsp_invitations_accepted_by_foreign')->nullOnDelete();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_share_pair_invitations');
    }
};
