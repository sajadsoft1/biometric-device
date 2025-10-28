<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->string('serial', 100)->unique()->comment('Device serial number');
            $table->string('name', 255)->comment('Device name/location');
            $table->string('type', 50)->default('aiface')->comment('Device type');
            $table->string('ip_address', 45)->nullable()->comment('Device IP address');
            $table->integer('port')->nullable()->comment('Device port');
            $table->boolean('is_online')->default(false)->index();
            $table->boolean('block')->default(false)->index()->comment('Block status');
            $table->dateTime('last_connected_at')->nullable();
            $table->dateTime('last_disconnected_at')->nullable();
            $table->json('extra_attributes')->nullable()->comment('Additional device info (firmware, capacity, etc.)');
            $table->timestamps();

            // Indexes
            $table->index(['serial', 'is_online']);
            $table->index(['type', 'block']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
