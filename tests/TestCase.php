<?php

declare(strict_types=1);

namespace Sajadsoft\BiometricDevices\Tests;

use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Facade;
use PHPUnit\Framework\TestCase as PHPUnit;
use Sajadsoft\BiometricDevices\Models\Device;
use Sajadsoft\BiometricDevices\Models\DeviceCommand;

abstract class TestCase extends PHPUnit
{
    protected Container $app;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app = new Container;
        Container::setInstance($this->app);
        Facade::setFacadeApplication($this->app);

        $this->app->instance('config', new Repository([
            'biometric-devices' => [
                'logging' => ['enabled' => false],
                'retry' => [
                    'max_attempts' => 3,
                    'response_timeout' => 60,
                ],
                'models' => [
                    'device' => Device::class,
                    'device_command' => DeviceCommand::class,
                ],
                'tasks' => [
                    'command_batch_size' => 10,
                    'ping_interval' => 15,
                    'ping_timeout' => 60,
                    'registration_timeout' => 30,
                    'command_check_interval' => 1,
                ],
                'websocket' => [
                    'backlog' => 128,
                    'read_chunk_bytes' => 65536,
                    'max_handshake_bytes' => 16384,
                    'max_payload_bytes' => 4194304,
                    'max_buffer_bytes' => 8388608,
                    'write_timeout_ms' => 1000,
                    'slow_handler_threshold_ms' => 500,
                ],
            ],
        ]));
        $this->app->instance('events', new Dispatcher($this->app));

        $database = new Capsule($this->app);
        $database->addConnection([
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $database->setEventDispatcher($this->app['events']);
        $database->setAsGlobal();
        $database->bootEloquent();
        $this->app->instance('db', $database->getDatabaseManager());

        $this->createDatabaseSchema($database);
    }

    protected function tearDown(): void
    {
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(null);
        Container::setInstance(null);

        parent::tearDown();
    }

    private function createDatabaseSchema(Capsule $database): void
    {
        $database->schema()->create('devices', function (Blueprint $table): void {
            $table->id();
            $table->string('serial')->unique();
            $table->string('name');
            $table->string('type')->default('aiface');
            $table->string('ip_address')->nullable();
            $table->integer('port')->nullable();
            $table->boolean('is_online')->default(false);
            $table->boolean('block')->default(false);
            $table->dateTime('last_connected_at')->nullable();
            $table->dateTime('last_disconnected_at')->nullable();
            $table->json('extra_attributes')->nullable();
            $table->timestamps();
        });

        $database->schema()->create('device_commands', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('device_id');
            $table->string('command_name');
            $table->longText('command_content')->nullable();
            $table->string('status')->default('pending');
            $table->boolean('send_status')->default(false);
            $table->integer('error_count')->default(0);
            $table->longText('error_message')->nullable();
            $table->dateTime('executed_at')->nullable();
            $table->longText('response')->nullable();
            $table->timestamps();
        });
    }
}
