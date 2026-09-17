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
        Schema::create('oauth_providers', function (Blueprint $table) {
            $table->increments('id');
            $table->char('uuid', 36)->unique();
            $table->string('name');
            $table->string('color', 7)->default('#2563eb');
            $table->string('logo')->nullable();
            $table->boolean('enabled')->default(false);
            $table->unsignedInteger('sort_order')->default(0);

            $table->string('client_id');
            $table->text('client_secret');
            $table->string('authorize_url');
            $table->string('token_url');
            $table->string('userinfo_url');
            $table->string('scopes')->nullable();
            $table->string('token_auth_method', 32)->default('client_secret_post');
            $table->boolean('use_pkce')->default(true);

            $table->string('identifier_field')->default('sub');
            $table->string('email_field')->default('email');
            $table->string('username_field')->nullable();
            $table->string('first_name_field')->nullable();
            $table->string('last_name_field')->nullable();

            $table->boolean('link_by_email')->default(false);
            $table->boolean('allow_registration')->default(false);
            $table->text('allowed_domains')->nullable();

            $table->timestamps();
        });

        Schema::create('user_oauth_identities', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('provider_id');
            $table->string('provider_user_id');
            $table->string('email')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->unique(['provider_id', 'provider_user_id']);
            $table->unique(['provider_id', 'user_id']);

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('provider_id')->references('id')->on('oauth_providers')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_oauth_identities');
        Schema::dropIfExists('oauth_providers');
    }
};
