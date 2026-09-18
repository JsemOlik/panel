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
        Schema::create('server_resource_stat_rollups', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('server_id');
            // Floored to the start of the hour this bucket summarizes, UTC.
            $table->timestamp('bucket_start');
            $table->float('cpu_avg');
            $table->float('cpu_max');
            $table->unsignedBigInteger('memory_avg_bytes');
            $table->unsignedBigInteger('memory_max_bytes');
            $table->unsignedBigInteger('disk_avg_bytes');
            // Throughput across the bucket (delta, not a cumulative counter) so charts can
            // plot it directly.
            $table->unsignedBigInteger('network_rx_bytes');
            $table->unsignedBigInteger('network_tx_bytes');
            $table->unsignedSmallInteger('sample_count');
            $table->timestamps();

            $table->foreign('server_id')->references('id')->on('servers')->cascadeOnDelete();
            $table->unique(['server_id', 'bucket_start']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('server_resource_stat_rollups');
    }
};
