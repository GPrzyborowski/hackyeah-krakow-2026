<?php

use App\Enums\ContractType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Contract types offered for the position (one or more), backfilled to an employment contract for existing offers.
     */
    public function up(): void
    {
        Schema::table('job_offers', function (Blueprint $table) {
            $table->json('contract_types')->nullable()->after('employment_fraction');
        });

        DB::table('job_offers')->update([
            'contract_types' => json_encode([ContractType::EmploymentContract->value]),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('job_offers', function (Blueprint $table) {
            $table->dropColumn('contract_types');
        });
    }
};
