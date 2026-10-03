<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A conversation is either a 1:1 chat opened by an invitation or the shared team chat of a job-sharing pair.
     */
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->unsignedBigInteger('invitation_id')->nullable()->change();
            $table->foreignId('job_share_pair_id')->nullable()->unique()->after('invitation_id')->constrained()->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('job_share_pair_id');
        });

        DB::table('conversations')->whereNull('invitation_id')->delete();

        Schema::table('conversations', function (Blueprint $table) {
            $table->unsignedBigInteger('invitation_id')->nullable(false)->change();
        });
    }
};
