<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Sajadsoft\BiometricDevices\Enums\DeviceCommandStatusEnum;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('device_commands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->index()->constrained('devices')->cascadeOnDelete();
            $table->string('command_name', 255)->comment('getuserlist, setuserinfo, deleteuser, etc.');
            $table->mediumText('command_content')->nullable()->comment('JSON command payload');
            $table->string('status')->index()->default(DeviceCommandStatusEnum::PENDING->value);
            $table->boolean('send_status')->index()->default(false);
            $table->integer('error_count')->default(0);
            $table->text('error_message')->nullable();
            $table->dateTime('executed_at')->nullable();
            $table->text('response')->nullable();
            $table->timestamps();

            // Indexes for better performance
            $table->index(['device_id', 'status', 'send_status']);
            $table->index(['device_id', 'command_name', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_commands');
    }
};
