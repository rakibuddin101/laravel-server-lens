<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('server_lens_ip_blocks', function (Blueprint $table) {
            $table->id();
            $table->string('ip_address', 45)->unique();
            $table->string('reason', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('expires_at')->nullable();
            $table->string('blocked_by', 100)->nullable();
            $table->timestamps();

            $table->index(['ip_address', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('server_lens_ip_blocks');
    }
};
