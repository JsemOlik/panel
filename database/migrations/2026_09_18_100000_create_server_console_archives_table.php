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
        Schema::create('server_console_archives', function (Blueprint $table) {
            // bigIncrements rather than the plain incrementing id used elsewhere in this fork
            // (e.g. egg_console_shortcuts): fleet-wide console capture is expected to grow well
            // past the ~4 billion row ceiling of a regular unsigned int over the life of the
            // install, unlike small admin-managed tables.
            $table->bigIncrements('id');
            $table->unsignedInteger('server_id');
            // Event time as reported by the ingestion daemon when it received the line, not the
            // time the batch was inserted — the two can differ by up to the batch flush window.
            $table->timestamp('logged_at');
            $table->text('line');
            // Best-effort classification of the line, see ConsoleLineClassifier. Never authoritative:
            // the raw `line` is always kept regardless of how (or whether) it was classified.
            $table->string('source', 16)->default('console');
            $table->string('player', 64)->nullable();
            $table->fullText(['line']);
            $table->index(['server_id', 'logged_at']);
            $table->index(['server_id', 'player']);
            $table->foreign('server_id')->references('id')->on('servers')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('server_console_archives');
    }
};
