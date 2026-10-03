<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Photo and phone are private contact data, revealed only to a company whose invitation the candidate accepted.
     */
    public function up(): void
    {
        Schema::table('candidate_profiles', function (Blueprint $table) {
            $table->string('photo_path')->nullable()->after('cv_text');
            $table->string('phone', 20)->nullable()->after('photo_path');
        });
    }

    public function down(): void
    {
        Schema::table('candidate_profiles', function (Blueprint $table) {
            $table->dropColumn(['photo_path', 'phone']);
        });
    }
};
