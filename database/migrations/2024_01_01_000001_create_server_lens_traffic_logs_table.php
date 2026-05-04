<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('server_lens_traffic_logs', function (Blueprint $table) {
            $table->id();
            $table->string('ip_address', 45);
            $table->string('user_agent', 512)->nullable();
            $table->string('method', 10)->default('GET');
            $table->string('url', 2048)->nullable();
            $table->string('path', 768)->nullable();
            $table->unsignedSmallInteger('status_code')->default(200);
            $table->decimal('response_time', 10, 2)->nullable();
            $table->string('referrer', 2048)->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('session_id', 128)->nullable();
            $table->string('classification', 20)->default('human');
            $table->string('action_taken', 20)->nullable();
            $table->string('country_code', 5)->nullable();
            $table->string('country_name', 100)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('bot_name', 100)->nullable();
            $table->boolean('is_ajax')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();

            $table->index(['created_at', 'status_code']);
            $table->index(['classification']);
            $table->index(['ip_address']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('server_lens_traffic_logs');
    }
};
