<?php

use App\Enums\OfferCategory;
use App\Services\Offers\OfferCategoryGuesser;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Industry category of the offer (one per offer), backfilled for existing offers from the title.
     */
    public function up(): void
    {
        Schema::table('job_offers', function (Blueprint $table) {
            $table->string('category')->default(OfferCategory::Other->value)->after('title')->index();
        });

        $guesser = new OfferCategoryGuesser;

        DB::table('job_offers')->select(['id', 'title'])->orderBy('id')->each(function (object $offer) use ($guesser): void {
            $category = $guesser->fromTitle((string) $offer->title);

            if ($category !== OfferCategory::Other) {
                DB::table('job_offers')->where('id', $offer->id)->update(['category' => $category->value]);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('job_offers', function (Blueprint $table) {
            $table->dropIndex(['category']);
            $table->dropColumn('category');
        });
    }
};
