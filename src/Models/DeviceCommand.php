<?php

declare(strict_types=1);

namespace Sajadsoft\BiometricDevices\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Sajadsoft\BiometricDevices\Enums\DeviceCommandStatusEnum;

/**
 * Device Command Model
 *
 * @property int                     $id
 * @property int                     $device_id
 * @property string                  $command_name
 * @property string|null             $command_content
 * @property DeviceCommandStatusEnum $status
 * @property bool                    $send_status
 * @property int                     $error_count
 * @property string|null             $error_message
 * @property \Carbon\Carbon|null     $executed_at
 * @property string|null             $response
 * @property \Carbon\Carbon          $created_at
 * @property \Carbon\Carbon          $updated_at
 */
class DeviceCommand extends Model
{
    use HasFactory;

    protected $fillable = [
        'device_id',
        'command_name',
        'command_content',
        'status',
        'send_status',
        'error_count',
        'error_message',
        'executed_at',
        'response',
    ];

    protected function casts(): array
    {
        return [
            'send_status' => 'boolean',
            'error_count' => 'integer',
            'executed_at' => 'datetime',
            'status'      => DeviceCommandStatusEnum::class,
        ];
    }

    /** Get the device that owns the command */
    public function device(): BelongsTo
    {
        // استفاده از Model پروژه کاربر
        $deviceModel = config('biometric-devices.models.device', \App\Models\Device::class);

        return $this->belongsTo($deviceModel);
    }

    /** Scope for pending commands */
    public function scopePending($query)
    {
        return $query->where('status', DeviceCommandStatusEnum::PENDING);
    }

    /** Scope for sent commands */
    public function scopeSent($query)
    {
        return $query->where('status', DeviceCommandStatusEnum::SENT);
    }

    /** Scope for successful commands */
    public function scopeSuccess($query)
    {
        return $query->where('status', DeviceCommandStatusEnum::SUCCESS);
    }

    /** Scope for failed commands */
    public function scopeFailed($query)
    {
        return $query->where('status', DeviceCommandStatusEnum::FAILED);
    }

    /** Get command data as array */
    public function getCommandDataAttribute(): ?array
    {
        return $this->command_content ? json_decode($this->command_content, true) : null;
    }

    /** Get response data as array */
    public function getResponseDataAttribute(): ?array
    {
        return $this->response ? json_decode($this->response, true) : null;
    }

    /** Mark command as sent */
    public function markAsSent(): bool
    {
        return $this->update([
            'status'      => DeviceCommandStatusEnum::SENT,
            'send_status' => true,
        ]);
    }

    /** Mark command as successful */
    public function markAsSuccess(?array $responseData = null): bool
    {
        return $this->update([
            'status'      => DeviceCommandStatusEnum::SUCCESS,
            'executed_at' => now(),
            'response'    => $responseData ? json_encode($responseData) : null,
        ]);
    }

    /** Mark command as failed */
    public function markAsFailed(?string $errorMessage = null, ?array $responseData = null): bool
    {
        return $this->update([
            'status'        => DeviceCommandStatusEnum::FAILED,
            'error_count'   => $this->error_count + 1,
            'error_message' => $errorMessage,
            'executed_at'   => now(),
            'response'      => $responseData ? json_encode($responseData) : null,
        ]);
    }

    /** Increment retry count */
    public function incrementRetryCount(): bool
    {
        return $this->update([
            'error_count' => $this->error_count + 1,
        ]);
    }

    /** Check if max retry attempts exceeded */
    public function hasExceededMaxRetries(): bool
    {
        $maxAttempts = config('biometric-devices.retry.max_attempts', 3);

        return $this->error_count >= $maxAttempts;
    }
}
