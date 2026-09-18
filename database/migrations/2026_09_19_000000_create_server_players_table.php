<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    /**
     * One row per (server, player name) ever observed on that server, upserted on every join/leave
     * event derived from Pterodactyl\Events\ConsoleArchive\ConsoleLinesCaptured. `status` flips
     * in place rather than the row being deleted on leave, so "last seen" stays queryable for
     * offline players. See app/Services/Players/PlayerPresenceService.php.
     */
    public function up(): void
    {
        Schema::create('server_players', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('server_id');
            // In-game name as last observed in console output. Minecraft names are capped at 16
            // characters, but 64 mirrors server_console_archives.player so both tables can join
            // cleanly on the same width and this stays usable for a proxy format with longer names.
            $table->string('name', 64);
            $table->string('status', 16)->default('offline');
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->foreign('server_id')->references('id')->on('servers')->cascadeOnDelete();
            $table->unique(['server_id', 'name']);
            $table->index(['server_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('server_players');
    }
};
