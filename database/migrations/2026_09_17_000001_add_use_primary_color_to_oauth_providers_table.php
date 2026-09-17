<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('oauth_providers', function (Blueprint $table) {
            $table->boolean('use_primary_color')->default(false)->after('color');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('oauth_providers', function (Blueprint $table) {
            $table->dropColumn('use_primary_color');
        });
    }
};
