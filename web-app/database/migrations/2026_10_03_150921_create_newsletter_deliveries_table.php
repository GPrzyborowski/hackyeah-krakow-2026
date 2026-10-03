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
        Schema::create('newsletter_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('newsletter_subscriber_id')->constrained()->cascadeOnDelete();
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->timestamp('sent_at');

            $table->unique(['newsletter_subscriber_id', 'article_id']);
        });

        Schema::table('newsletter_subscribers', function (Blueprint $table) {
            $table->timestamp('last_sent_at')->nullable()->after('unsubscribed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('newsletter_subscribers', function (Blueprint $table) {
            $table->dropColumn('last_sent_at');
        });

        Schema::dropIfExists('newsletter_deliveries');
    }
};
