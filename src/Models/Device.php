<?php

declare(strict_types=1);

namespace Sajadsoft\BiometricDevices\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Sajadsoft\BiometricDevices\Enums\DeviceCommandStatusEnum;

/**
 * Device Model
 *
 * @property int                 $id
 * @property string              $serial
 * @property string              $name
 * @property string|null         $ip_address
 * @property int|null            $port
 * @property bool                $is_online
 * @property \Carbon\Carbon|null $last_connected_at
 * @property \Carbon\Carbon|null $last_disconnected_at
 * @property array|null          $extra_attributes
 * @property \Carbon\Carbon      $created_at
 * @property \Carbon\Carbon      $updated_at
 */
class Device extends Model
{
    use HasFactory;

    protected $fillable = [
        'serial',
        'name',
        'ip_address',
        'port',
        'is_online',
        'last_connected_at',
        'last_disconnected_at',
        'extra_attributes',
    ];

    protected function casts(): array
    {
        return [
            'is_online'            => 'boolean',
            'last_connected_at'    => 'datetime',
            'last_disconnected_at' => 'datetime',
            'extra_attributes'     => 'array',
        ];
    }

    /** Get all commands for this device */
    public function commands(): HasMany
    {
        $commandModel = config('biometric-devices.models.device_command', DeviceCommand::class);

        return $this->hasMany($commandModel);
    }

    /** Get pending commands */
    public function pendingCommands(): HasMany
    {
        return $this->commands()->where('status', DeviceCommandStatusEnum::PENDING);
    }

    /** Get sent commands */
    public function sentCommands(): HasMany
    {
        return $this->commands()->where('status', DeviceCommandStatusEnum::SENT);
    }

    /** Get successful commands */
    public function successCommands(): HasMany
    {
        return $this->commands()->where('status', DeviceCommandStatusEnum::SUCCESS);
    }

    /** Get failed commands */
    public function failedCommands(): HasMany
    {
        return $this->commands()->where('status', DeviceCommandStatusEnum::FAILED);
    }

    /** Check if device is online */
    public function isOnline(): bool
    {
        return (bool) $this->is_online;
    }

    /** Mark device as online */
    public function markAsOnline(): void
    {
        $this->update([
            'is_online'         => true,
            'last_connected_at' => now(),
        ]);
    }

    /** Mark device as offline */
    public function markAsOffline(): void
    {
        $this->update([
            'is_online'            => false,
            'last_disconnected_at' => now(),
        ]);
    }

    /** بروزرسانی اطلاعات اضافی دستگاه */
    public function updateDeviceInfo(array $extraAttributes = []): void
    {
        $this->update([
            'extra_attributes' => array_merge($this->extra_attributes ?? [], $extraAttributes),
        ]);
    }

    /** Scope برای دستگاه‌های آنلاین */
    public function scopeOnline($query)
    {
        return $query->where('is_online', true);
    }

    /** Scope برای دستگاه‌های آفلاین */
    public function scopeOffline($query)
    {
        return $query->where('is_online', false);
    }
}
