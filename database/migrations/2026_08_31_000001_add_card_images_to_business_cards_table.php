<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Photos of the physical card: front_image is the side OCR reads to
     * auto-fill the form, back_image is stored for reference only.
     */
    public function up(): void
    {
        Schema::table('business_cards', function (Blueprint $table) {
            $table->string('front_image')->nullable()->after('profile_image');
            $table->string('back_image')->nullable()->after('front_image');
        });
    }

    public function down(): void
    {
        Schema::table('business_cards', function (Blueprint $table) {
            $table->dropColumn(['front_image', 'back_image']);
        });
    }
};
