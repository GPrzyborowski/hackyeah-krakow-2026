<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Distance in km from the workplace to the nearest nursery or kindergarten (null = not provided).
     */
    public function up(): void
    {
        Schema::table('job_offers', function (Blueprint $table) {
            $table->unsignedTinyInteger('nursery_distance_km')->nullable()->after('childcare_subsidy');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('job_offers', function (Blueprint $table) {
            $table->dropColumn('nursery_distance_km');
        });
    }
};
