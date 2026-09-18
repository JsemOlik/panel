<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    /**
     * Append-only join/leave history, one row per detected event. This is the source for a
     * per-player activity view ("what has this player been doing") and survives roster upserts to
     * server_players, which only keep the current/last-known state.
     *
     * Deliberately NOT foreign-keyed to server_players (which is keyed by name, not a stable
     * player id) — a name can be reused/renamed, so this table is queried by (server_id, name) or
     * just name for a fleet-wide player history, same as server_console_archives.player.
     */
    public function up(): void
    {
        Schema::create('server_player_sessions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('server_id');
            $table->string('name', 64);
            $table->string('event', 16); // 'join' | 'leave'
            $table->timestamp('occurred_at');
            $table->timestamp('created_at')->nullable();

            $table->foreign('server_id')->references('id')->on('servers')->cascadeOnDelete();
            $table->index(['server_id', 'name', 'occurred_at']);
            $table->index(['name', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('server_player_sessions');
    }
};
