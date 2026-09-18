<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('keyword_alert_rules', function (Blueprint $table) {
            $table->increments('id');
            $table->string('uuid', 36)->unique();
            $table->string('label')->comment('Short admin-facing name for the rule, e.g. "Self-harm language".');
            $table->text('phrase')->comment('The literal term or regex pattern to match against console/chat lines.');
            $table->enum('match_type', ['substring', 'word', 'regex'])->default('word');
            $table->enum('severity', ['info', 'warning', 'critical'])->default('warning');
            $table->boolean('case_sensitive')->default(false);
            $table->boolean('enabled')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('keyword_alert_rules');
    }
};
