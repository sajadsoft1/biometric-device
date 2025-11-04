<?php

declare(strict_types=1);

namespace Sajadsoft\BiometricDevices;

use Illuminate\Contracts\Foundation\Application;
use Sajadsoft\BiometricDevices\Contracts\DataMapperInterface;
use Sajadsoft\BiometricDevices\DTOs\Commands\AddUserDTO;
use Sajadsoft\BiometricDevices\DTOs\Commands\EnrollFingerprintDTO;
use Sajadsoft\BiometricDevices\DTOs\Commands\GetUserInfoDTO;
use Sajadsoft\BiometricDevices\DTOs\Commands\SetUserAccessDTO;
use Sajadsoft\BiometricDevices\Enums\BiometricType;
use Sajadsoft\BiometricDevices\Enums\DeviceCommandStatusEnum;
use Sajadsoft\BiometricDevices\Events\CommandSent;
use Sajadsoft\BiometricDevices\Models\Device;
use Sajadsoft\BiometricDevices\Models\DeviceCommand;
use Sajadsoft\BiometricDevices\Support\Logger;

/**
 * Main manager class for biometric device operations
 */
class BiometricDeviceManager
{
    protected Application $app;

    protected DataMapperInterface $mapper;

    public function __construct(Application $app)
    {
        $this->app = $app;

        // Load mapper از config
        $mapperClass  = config('biometric-devices.mappers.zkteco-websocket');
        $this->mapper = new $mapperClass;
    }

    // ============================================
    // User Management
    // ============================================

    /**
     * Send command to add user
     *
     * @param string     $deviceSerial شماره سریال دستگاه
     * @param AddUserDTO $dto          اطلاعات کاربر شامل نام، نوع بیومتریک و داده‌های بیومتریک
     */
    public function addUser(string $deviceSerial, AddUserDTO $dto): void
    {
        $command = $this->mapper->mapAddUserCommand($dto);

        $this->sendRawCommand($deviceSerial, 'setuserinfo', $command, $dto);
    }

    /**
     * Send command to delete user
     *
     * @param string             $deviceSerial  شماره سریال دستگاه
     * @param int                $employeeId    شناسه کاربر
     * @param BiometricType|null $biometricType نوع بیومتریک برای حذف (null = حذف کامل کاربر)
     */
    public function deleteUser(string $deviceSerial, int $employeeId, ?BiometricType $biometricType = null): void
    {
        $dto = new DTOs\Commands\DeleteUserDTO(
            employeeId: $employeeId,
            biometricType: $biometricType
        );

        $command = $this->mapper->mapDeleteUserCommand($dto);

        $this->sendRawCommand($deviceSerial, 'deleteuser', $command, $dto);
    }

    /**
     * Start fingerprint enrollment on device
     *
     * دستگاه را وارد حالت ثبت اثر انگشت می‌کند
     * دستگاه منتظر می‌ماند تا کاربر 3 بار انگشت خود را روی سنسور قرار دهد
     *
     * بعد از فراخوانی این متد، باید به صورت مداوم checkRegistrationStatus را فراخوانی کنید
     * تا وضعیت فرآیند ثبت را دریافت کنید (status: 1, 2, 3, 100)
     *
     * @param string        $deviceSerial  شماره سریال دستگاه
     * @param int           $employeeId    شناره کاربر
     * @param BiometricType $biometricType نوع بیومتریک (معمولاً FINGERPRINT_1 تا FINGERPRINT_10)
     * @param int           $flag          پرچم دستور (پیش‌فرض 2)
     *
     * @example
     * BiometricDevice::enrollFingerprint('DEVICE_SERIAL', 123, BiometricType::FINGERPRINT_1);
     * // سپس به صورت مداوم checkRegistrationStatus را فراخوانی کنید
     */
    public function enrollFingerprint(
        string $deviceSerial,
        int $employeeId,
        BiometricType $biometricType,
        int $flag = 2
    ): void {
        $dto = new EnrollFingerprintDTO(
            employeeId: $employeeId,
            biometricType: $biometricType,
            flag: $flag
        );

        $command = $this->mapper->mapEnrollFingerprintCommand($dto);

        $this->sendRawCommand($deviceSerial, 'adduser', $command, $dto);
    }

    /**
     * Check registration status during fingerprint enrollment
     *
     * این متد باید به صورت polling (هر 1-2 ثانیه) فراخوانی شود
     * تا وضعیت فرآیند ثبت اثر انگشت را دریافت کنید
     *
     * @param string $deviceSerial شماره سریال دستگاه
     *
     * @example
     * // در یک loop یا با setInterval
     * BiometricDevice::checkRegistrationStatus('DEVICE_SERIAL');
     */
    public function checkRegistrationStatus(string $deviceSerial): void
    {
        $this->sendRawCommand($deviceSerial, 'checkregstatus', ['cmd' => 'checkregstatus']);
    }

    /**
     * Send command to get user list
     *
     * @param string $deviceSerial       شماره سریال دستگاه
     * @param bool   $startFromBeginning شروع از ابتدا یا ادامه لیست قبلی
     */
    public function getUserList(string $deviceSerial, bool $startFromBeginning = true): void
    {
        $command = [
            'cmd' => 'getuserlist',
            'stn' => $startFromBeginning,
        ];

        $this->sendRawCommand($deviceSerial, 'getuserlist', $command);
    }

    /**
     * Send command to get user info
     *
     * @param string             $deviceSerial  شماره سریال دستگاه
     * @param int                $employeeId    شناسه کاربر
     * @param BiometricType|null $biometricType نوع بیومتریک (null = همه نوع‌ها)
     */
    public function getUserInfo(string $deviceSerial, int $employeeId, ?BiometricType $biometricType): void
    {
        $dto = new GetUserInfoDTO(
            employeeId: $employeeId,
            biometricType: $biometricType
        );

        $command = $this->mapper->mapGetUserInfoCommand($dto);

        $this->sendRawCommand($deviceSerial, 'getuserinfo', $command, $dto);
    }

    /**
     * Set usernames in batch mode
     *
     * دستور setusername برای بروزرسانی دسته‌جمعی نام کاربران بدون تغییر اطلاعات بیومتریک
     *
     * @param string                                    $deviceSerial شماره سریال دستگاه
     * @param array<array{enrollid: int, name: string}> $usernames    لیست کاربران با enrollid و name
     *
     * @example
     * $usernames = [
     *     ['enrollid' => 1, 'name' => 'علی احمدی'],
     *     ['enrollid' => 2, 'name' => 'رضا محمدی'],
     * ];
     * BiometricDevice::setUsernames('DEVICE_001', $usernames);
     */
    public function setUsernames(string $deviceSerial, array $usernames): void
    {
        $command = [
            'cmd'    => 'setusername',
            'count'  => count($usernames),
            'record' => array_map(fn ($item) => [
                'enrollid' => $item['enrollid'],
                'name'     => $item['name'],
            ], $usernames),
        ];

        $this->sendRawCommand($deviceSerial, 'setusername', $command);
    }

    /**
     * Clean all admin users from device
     *
     * ⚠️ این دستور خطرناک است و تمام کاربران با سطح دسترسی Admin را حذف می‌کند
     *
     * @param string $deviceSerial شماره سریال دستگاه
     */
    public function cleanAdmin(string $deviceSerial): void
    {
        $this->sendRawCommand($deviceSerial, 'cleanadmin', ['cmd' => 'cleanadmin']);
    }

    // ============================================
    // Device Control
    // ============================================

    /**
     * Send command to open door
     *
     * @param string $deviceSerial شماره سریال دستگاه
     * @param int    $doorNumber   شماره درب (برای دستگاه‌های چند دربه)
     * @param int    $duration     مدت زمان باز ماندن درب به ثانیه
     */
    public function openDoor(string $deviceSerial, int $doorNumber = 1, int $duration = 5): void
    {
        $dto = new DTOs\Commands\OpenDoorDTO(
            doorNumber: $doorNumber,
            duration: $duration
        );

        $command = $this->mapper->mapOpenDoorCommand($dto);

        $this->sendRawCommand($deviceSerial, 'opendoor', $command, $dto);
    }

    /**
     * Send command to get device info
     *
     * @param string $deviceSerial شماره سریال دستگاه
     */
    public function getDeviceInfo(string $deviceSerial): void
    {
        $this->sendRawCommand($deviceSerial, 'getdevinfo', ['cmd' => 'getdevinfo']);
    }

    /**
     * Reboot device
     *
     * ⚠️ دستگاه بلافاصله restart می‌شود و پاسخی ارسال نمی‌کند
     *
     * @param string $deviceSerial شماره سریال دستگاه
     */
    public function reboot(string $deviceSerial): void
    {
        $this->sendRawCommand($deviceSerial, 'reboot', ['cmd' => 'reboot']);
    }

    /**
     * Initialize system (Factory Reset)
     *
     * ⚠️ این دستور خطرناک است و تمام داده‌های دستگاه را پاک می‌کند
     * (کاربران، لاگ‌ها، تنظیمات و...)
     *
     * @param string $deviceSerial شماره سریال دستگاه
     */
    public function initSystem(string $deviceSerial): void
    {
        $this->sendRawCommand($deviceSerial, 'initsys', ['cmd' => 'initsys']);
    }

    /**
     * Set device time
     *
     * @param string         $deviceSerial شماره سریال دستگاه
     * @param \Carbon\Carbon $datetime     زمان جدید برای تنظیم
     */
    public function setTime(string $deviceSerial, \Carbon\Carbon $datetime): void
    {
        $dto = new DTOs\Commands\SetTimeDTO(
            datetime: $datetime
        );

        $command = $this->mapper->mapSetTimeCommand($dto);

        $this->sendRawCommand($deviceSerial, 'settime', $command, $dto);
    }

    // ============================================
    // Access Control
    // ============================================

    /**
     * Set user access permissions
     *
     * تنظیم دسترسی زمان‌بندی شده برای کاربر (Time Zone، Week Schedule)
     *
     * @param string           $deviceSerial شماره سریال دستگاه
     * @param SetUserAccessDTO $dto          اطلاعات دسترسی شامل employeeId، weekZone، group، startDate، endDate
     */
    public function setUserAccess(string $deviceSerial, SetUserAccessDTO $dto): void
    {
        $command = $this->mapper->mapSetUserAccessCommand($dto);

        $this->sendRawCommand($deviceSerial, 'setuserlock', $command, $dto);
    }

    /**
     * Set device lock status
     *
     * قفل یا باز کردن دستگاه (وقتی قفل باشد هیچ کاربری نمی‌تواند تردد کند)
     *
     * @param string $deviceSerial شماره سریال دستگاه
     * @param bool   $locked       true = قفل، false = باز
     */
    public function setDeviceLock(string $deviceSerial, bool $locked): void
    {
        $dto = new DTOs\Commands\SetDeviceLockDTO(
            locked: $locked
        );

        $command = $this->mapper->mapSetDeviceLockCommand($dto);

        $this->sendRawCommand($deviceSerial, 'setdevlock', $command, $dto);
    }

    // ============================================
    // Attendance Logs
    // ============================================

    /**
     * Get all attendance logs from device
     *
     * @param string $deviceSerial       شماره سریال دستگاه
     * @param bool   $startFromBeginning شروع از ابتدا یا ادامه لیست قبلی
     */
    public function getAllLogs(string $deviceSerial, bool $startFromBeginning = true): void
    {
        $dto = new DTOs\Commands\GetLogsDTO(
            startFromBeginning: $startFromBeginning,
            newLogsOnly: false
        );

        $command = $this->mapper->mapGetLogsCommand($dto, 'getalllog');

        $this->sendRawCommand($deviceSerial, 'getalllog', $command, $dto);
    }

    /**
     * Get new attendance logs from device
     *
     * فقط لاگ‌های جدید که هنوز دریافت نشده‌اند
     *
     * @param string $deviceSerial       شماره سریال دستگاه
     * @param bool   $startFromBeginning شروع از ابتدا یا ادامه لیست قبلی
     */
    public function getNewLogs(string $deviceSerial, bool $startFromBeginning = true): void
    {
        $dto = new DTOs\Commands\GetLogsDTO(
            startFromBeginning: $startFromBeginning,
            newLogsOnly: true
        );

        $command = $this->mapper->mapGetLogsCommand($dto, 'getnewlog');

        $this->sendRawCommand($deviceSerial, 'getnewlog', $command, $dto);
    }

    /**
     * Clean all attendance logs from device
     *
     * ⚠️ این دستور تمام لاگ‌های حضور و غیاب را از دستگاه پاک می‌کند
     *
     * @param string $deviceSerial شماره سریال دستگاه
     */
    public function cleanLog(string $deviceSerial): void
    {
        $this->sendRawCommand($deviceSerial, 'cleanlog', ['cmd' => 'cleanlog']);
    }

    /**
     * Send raw command to device
     *
     * @param string     $deviceSerial شماره سریال دستگاه
     * @param string     $commandName  نام دستور
     * @param array      $params       پارامترهای دستور
     * @param mixed|null $dto          DTO مربوط به دستور (اختیاری)
     */
    protected function sendRawCommand(
        string $deviceSerial,
        string $commandName,
        array $params,
        mixed $dto = null
    ): void {
        // ذخیره خودکار دستور در دیتابیس (با params واقعی که به دستگاه میره)
        $command = $this->saveCommandToDatabase($deviceSerial, $commandName, $params);

        // ارسال Event برای اطلاع‌رسانی و پردازش‌های اضافی
        event(new CommandSent($deviceSerial, $commandName, $dto ?? (object) $params, $command));
    }

    /** ذخیره دستور در دیتابیس */
    protected function saveCommandToDatabase(string $deviceSerial, string $commandName, mixed $dto): ?DeviceCommand
    {
        $deviceModel  = config('biometric-devices.models.device', Device::class);
        $commandModel = config('biometric-devices.models.device_command', DeviceCommand::class);

        // بررسی وجود Model ها
        if ( ! class_exists($deviceModel) || ! class_exists($commandModel)) {
            return null;
        }

        // پیدا کردن دستگاه
        $device = $deviceModel::where('serial', $deviceSerial)->first();

        if ( ! $device) {
            Logger::debug('Device not found for command', [
                'serial'  => $deviceSerial,
                'command' => $commandName,
            ]);

            return null;
        }

        // بررسی تکراری نبودن (جلوگیری از duplicate)
        $recentCommand = $commandModel::where('device_id', $device->id)
            ->where('command_name', $commandName)
            ->where('created_at', '>=', now()->subSeconds(2))
            ->exists();

        if ($recentCommand) {
            Logger::debug('Duplicate command detected, skipping save', [
                'device_serial' => $deviceSerial,
                'command_name'  => $commandName,
            ]);

            return null;
        }

        // ذخیره command
        $command = $commandModel::create([
            'device_id'       => $device->id,
            'command_name'    => $commandName,
            'command_content' => json_encode($dto),
            'status'          => DeviceCommandStatusEnum::PENDING,
            'send_status'     => false,
        ]);

        return $command;
    }

    /** Get mapper instance */
    public function getMapper(): DataMapperInterface
    {
        return $this->mapper;
    }

    /** Set custom mapper */
    public function setMapper(DataMapperInterface $mapper): void
    {
        $this->mapper = $mapper;
    }
}
