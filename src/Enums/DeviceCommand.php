<?php

declare(strict_types=1);

namespace Sajadsoft\BiometricDevices\Enums;

enum DeviceCommand: string
{
    // Device Registration
    case REGISTER = 'reg';

    // User Management
    case GET_USER_LIST = 'getuserlist';
    case GET_USER_INFO = 'getuserinfo';
    case SET_USER_INFO = 'setuserinfo';
    case SEND_USER     = 'senduser';
    case DELETE_USER   = 'deleteuser';
    case SET_USER_NAME = 'setusername';
    case CLEAN_ADMIN   = 'cleanadmin';

    // Attendance Logs
    case SEND_LOG    = 'sendlog';
    case GET_ALL_LOG = 'getalllog';
    case GET_NEW_LOG = 'getnewlog';

    // Device Control
    case OPEN_DOOR       = 'opendoor';
    case REBOOT          = 'reboot';
    case GET_DEVICE_INFO = 'getdevinfo';
    case INIT_SYSTEM     = 'initsys';
    case SET_TIME        = 'settime';

    // Access Control
    case GET_DEVICE_LOCK = 'getdevlock';
    case SET_DEVICE_LOCK = 'setdevlock';
    case GET_USER_LOCK   = 'getuserlock';
    case SET_USER_LOCK   = 'setuserlock';

    // Other
    case SEND_QR_CODE = 'sendqrcode';
    case GET_COMMAND  = 'getcommand';
    case ACK_COMMAND  = 'ackcommand';

    /** Get the handler class for this command */
    public function getHandlerClass(): string
    {
        return match ($this) {
            self::REGISTER        => \Sajadsoft\BiometricDevices\Services\CommandHandlers\RegisterDeviceHandler::class,
            self::SEND_LOG        => \Sajadsoft\BiometricDevices\Services\CommandHandlers\SendLogHandler::class,
            self::SEND_QR_CODE    => \Sajadsoft\BiometricDevices\Services\CommandHandlers\SendQrCodeHandler::class,
            self::GET_USER_LIST   => \Sajadsoft\BiometricDevices\Services\CommandHandlers\GetUserListHandler::class,
            self::GET_USER_INFO   => \Sajadsoft\BiometricDevices\Services\CommandHandlers\GetUserInfoHandler::class,
            self::SET_USER_INFO   => \Sajadsoft\BiometricDevices\Services\CommandHandlers\SetUserInfoHandler::class,
            self::SEND_USER       => \Sajadsoft\BiometricDevices\Services\CommandHandlers\SendUserHandler::class,
            self::DELETE_USER     => \Sajadsoft\BiometricDevices\Services\CommandHandlers\DeleteUserHandler::class,
            self::GET_ALL_LOG     => \Sajadsoft\BiometricDevices\Services\CommandHandlers\GetAllLogHandler::class,
            self::GET_NEW_LOG     => \Sajadsoft\BiometricDevices\Services\CommandHandlers\GetNewLogHandler::class,
            self::OPEN_DOOR       => \Sajadsoft\BiometricDevices\Services\CommandHandlers\OpenDoorHandler::class,
            self::GET_DEVICE_INFO => \Sajadsoft\BiometricDevices\Services\CommandHandlers\GetDeviceInfoHandler::class,
            self::SET_DEVICE_LOCK => \Sajadsoft\BiometricDevices\Services\CommandHandlers\SetDeviceLockHandler::class,
            self::SET_USER_LOCK   => \Sajadsoft\BiometricDevices\Services\CommandHandlers\SetUserLockHandler::class,
            default               => \Sajadsoft\BiometricDevices\Services\CommandHandlers\DefaultCommandHandler::class,
        };
    }

    /** Check if this is a request from device to server */
    public function isDeviceRequest(): bool
    {
        return in_array($this, [
            self::REGISTER,
            self::SEND_LOG,
            self::SEND_USER,
            self::GET_COMMAND,
            self::ACK_COMMAND,
            self::SEND_QR_CODE,
        ]);
    }

    /** Check if this is a command from server to device */
    public function isServerCommand(): bool
    {
        return ! $this->isDeviceRequest();
    }

    /** Get display label */
    public function label(): string
    {
        return match ($this) {
            self::REGISTER        => 'ثبت دستگاه',
            self::GET_USER_LIST   => 'دریافت لیست کاربران',
            self::GET_USER_INFO   => 'دریافت اطلاعات کاربر',
            self::SET_USER_INFO   => 'تنظیم اطلاعات کاربر',
            self::SEND_USER       => 'ارسال اطلاعات کاربر',
            self::DELETE_USER     => 'حذف کاربر',
            self::SEND_LOG        => 'ارسال لاگ حضور',
            self::SEND_QR_CODE    => 'ارسال QR Code',
            self::GET_ALL_LOG     => 'دریافت تمام لاگ‌ها',
            self::GET_NEW_LOG     => 'دریافت لاگ‌های جدید',
            self::OPEN_DOOR       => 'باز کردن درب',
            self::REBOOT          => 'راه‌اندازی مجدد',
            self::GET_DEVICE_INFO => 'دریافت اطلاعات دستگاه',
            self::INIT_SYSTEM     => 'مقداردهی اولیه',
            self::SET_DEVICE_LOCK => 'تنظیم قفل دستگاه',
            self::SET_USER_LOCK   => 'تنظیم قفل کاربر',
            self::SET_TIME        => 'تنظیم زمان',
            default               => $this->value,
        };
    }
}
