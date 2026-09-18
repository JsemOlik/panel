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
        Schema::create('server_resource_samples', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('server_id');
            $table->timestamp('recorded_at');
            // Wings reports cpu_absolute as a percentage of one core; multi-core
            // allocations can push this well past 100, so store with headroom.
            $table->float('cpu_absolute');
            $table->unsignedBigInteger('memory_bytes');
            $table->unsignedBigInteger('disk_bytes');
            // Cumulative counters exactly as reported by Wings for this sample. Deltas
            // (and counter-reset handling) are computed by readers, not at write time.
            $table->unsignedBigInteger('network_rx_bytes');
            $table->unsignedBigInteger('network_tx_bytes');
            $table->string('state', 16);
            $table->timestamps();

            $table->foreign('server_id')->references('id')->on('servers')->cascadeOnDelete();
            $table->index(['server_id', 'recorded_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('server_resource_samples');
    }
};
