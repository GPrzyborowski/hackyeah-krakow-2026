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
        Schema::table('job_offers', function (Blueprint $table) {
            $table->boolean('is_job_share')->default(false)->after('childcare_subsidy');
            $table->time('workday_starts_at')->nullable()->after('is_job_share');
            $table->time('workday_ends_at')->nullable()->after('workday_starts_at');
        });

        Schema::table('candidate_profiles', function (Blueprint $table) {
            $table->string('preferred_day_part')->nullable()->after('open_to_job_sharing');
        });

        Schema::table('invitations', function (Blueprint $table) {
            $table->foreignId('job_share_pair_id')->nullable()->after('candidate_profile_id')->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invitations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('job_share_pair_id');
        });

        Schema::table('candidate_profiles', function (Blueprint $table) {
            $table->dropColumn('preferred_day_part');
        });

        Schema::table('job_offers', function (Blueprint $table) {
            $table->dropColumn(['is_job_share', 'workday_starts_at', 'workday_ends_at']);
        });
    }
};
