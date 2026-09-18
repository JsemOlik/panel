<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('subusers', function (Blueprint $table) {
            // Nullable: a NULL value means the grant never expires, matching the
            // existing convention used by api_keys.expires_at. A non-null value in
            // the past means the grant is expired and must be treated as if the
            // subuser row did not exist by every permission-resolution code path.
            $table->timestamp('expires_at')->nullable()->after('permissions');
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subusers', function (Blueprint $table) {
            $table->dropIndex(['expires_at']);
            $table->dropColumn('expires_at');
        });
    }
};
