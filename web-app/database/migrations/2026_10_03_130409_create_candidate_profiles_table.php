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
        Schema::create('candidate_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('headline')->nullable();
            $table->unsignedTinyInteger('years_of_experience')->nullable();
            $table->string('city')->nullable();
            $table->text('ai_summary')->nullable();
            $table->date('available_from')->nullable();
            $table->date('leave_starts_on')->nullable();
            $table->date('due_date')->nullable();
            $table->json('work_modes')->nullable();
            $table->json('employment_fractions')->nullable();
            $table->boolean('wants_flexible_hours')->default(false);
            $table->boolean('open_to_job_sharing')->default(false);
            $table->boolean('show_availability_instead_of_gap')->default(true);
            $table->foreignId('hidden_from_company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->boolean('allow_direct_messages')->default(false);
            $table->unsignedTinyInteger('onboarding_step')->default(1);
            $table->string('cv_path')->nullable();
            $table->string('cv_original_name')->nullable();
            $table->string('cv_status')->nullable();
            $table->longText('cv_text')->nullable();
            $table->json('suggested_positions')->nullable();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('candidate_profiles');
    }
};
