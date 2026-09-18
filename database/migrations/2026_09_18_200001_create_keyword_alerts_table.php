<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('keyword_alerts', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('rule_id');
            $table->unsignedInteger('server_id');
            $table->string('player')->nullable();
            $table->string('source', 16)->comment('ServerConsoleArchive::SOURCE_CONSOLE or SOURCE_CHAT, copied from the captured line.');
            $table->text('line')->comment('The full matched console/chat line, ANSI-stripped (already sanitized upstream by the archive ingestion daemon).');
            $table->string('matched_text')->comment('Just the substring the rule matched, for a quick-glance snippet.');
            $table->enum('severity', ['info', 'warning', 'critical']);
            $table->enum('status', ['open', 'resolved'])->default('open');
            $table->unsignedInteger('occurrence_count')->default(1)->comment('How many times this rule fired for this server within the dedup window; incremented instead of creating a new row/notification.');
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->timestamp('notified_at')->nullable();
            $table->unsignedInteger('resolved_by')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->foreign('rule_id')->references('id')->on('keyword_alert_rules')->cascadeOnDelete();
            $table->foreign('server_id')->references('id')->on('servers')->cascadeOnDelete();
            $table->foreign('resolved_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['status', 'severity']);
            $table->index(['server_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('keyword_alerts');
    }
};
