# راهنمای استفاده از پکیج BiometricDevices

این راهنما شامل نمونه‌های عملی و کاربردی برای استفاده از تمام قابلیت‌های پکیج است.

## فهرست مطالب

1. [نصب و راه‌اندازی](#نصب-و-راهاندازی)
2. [تنظیمات](#تنظیمات)
3. [مدیریت کاربران](#مدیریت-کاربران)
4. [کنترل دستگاه](#کنترل-دستگاه)
5. [مدیریت دسترسی](#مدیریت-دسترسی)
6. [مدیریت لاگ‌های حضور](#مدیریت-لاگهای-حضور)
7. [رویدادها (Events)](#رویدادها)
8. [استفاده پیشرفته](#استفاده-پیشرفته)

---

## نصب و راه‌اندازی

### 1. نصب پکیج

```bash
composer require sajadsoft1/biometric-device
```

### 2. انتشار فایل‌های پکیج

```bash
# انتشار فایل پیکربندی
php artisan vendor:publish --tag=biometric-devices-config

# اجرای migrations (به صورت خودکار load می‌شوند)
php artisan migrate
```

### 3. تنظیم متغیرهای محیطی

در فایل `.env` خود، تنظیمات زیر را اضافه کنید:

```env
# تنظیمات WebSocket
BIOMETRIC_WS_HOST=0.0.0.0
BIOMETRIC_WS_PORT=7788

# نوع Driver و دستگاه
BIOMETRIC_DRIVER=websocket
BIOMETRIC_DEVICE_TYPE=aiface

# لاگ‌گیری (اختیاری)
BIOMETRIC_LOG_ENABLED=true
BIOMETRIC_LOG_LEVEL=debug
```

### 4. راه‌اندازی سرور

```bash
php artisan biometric:start-server
```

یا با استفاده از Supervisor برای اجرای پایدار:

```ini
[program:biometric-server]
process_name=%(program_name)s
command=php /path/to/artisan biometric:start-server
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/path/to/logs/biometric-server.log
```

---

## تنظیمات

### استفاده از Facade

برای راحتی در استفاده، می‌توانید از Facade استفاده کنید:

```php
use Sajadsoft\BiometricDevices\Facades\BiometricDevice;
```

### دریافت لیست دستگاه‌های متصل

```php
use Sajadsoft\BiometricDevices\Facades\BiometricDevice;
use Sajadsoft\BiometricDevices\Models\Device;

// از طریق پکیج (دستگاه‌های فعال در سرور WebSocket)
$driver = app(\Sajadsoft\BiometricDevices\Contracts\DeviceDriverInterface::class);
$connectedDevices = $driver->getConnectedDevices();

// از طریق دیتابیس (دستگاه‌های آنلاین)
$onlineDevices = Device::online()->get();
```

---

## مدیریت کاربران

### 1. اضافه کردن کاربر با اثر انگشت (Fingerprint)

```php
use Sajadsoft\BiometricDevices\Facades\BiometricDevice;
use Sajadsoft\BiometricDevices\DTOs\Commands\AddUserDTO;
use Sajadsoft\BiometricDevices\Enums\BiometricType;

// اضافه کردن کاربر با fingerprint
$addUser = new AddUserDTO(
    employeeId: 1001,
    name: 'علی احمدی',
    biometricType: BiometricType::FINGERPRINT_0, // اولین انگشت
    biometricData: 'BASE64_FINGERPRINT_TEMPLATE_DATA',
    isAdmin: false
);

BiometricDevice::addUser('DEVICE_SERIAL_NUMBER', $addUser);
```

**نکات مهم:**
- `biometricData` باید template اثر انگشت به صورت Base64 باشد
- `BiometricType::FINGERPRINT_0` تا `FINGERPRINT_9` برای 10 انگشت مختلف
- برای هر کاربر می‌توانید چند اثر انگشت مختلف ثبت کنید

### 2. اضافه کردن کاربر با تشخیص چهره (Face Recognition)

```php
$addFaceUser = new AddUserDTO(
    employeeId: 1002,
    name: 'محمد رضایی',
    biometricType: BiometricType::FACE_0, // اولین چهره
    biometricData: 'BASE64_FACE_TEMPLATE_DATA',
    isAdmin: false
);

BiometricDevice::addUser('DEVICE_SERIAL_NUMBER', $addFaceUser);
```

**نکات:**
- `BiometricType::FACE_0` تا `FACE_7` برای 8 چهره مختلف
- دستگاه‌های Face Recognition معمولاً عکس چهره را خودشان ثبت می‌کنند

### 3. اضافه کردن کاربر با کارت (Card)

```php
$addCardUser = new AddUserDTO(
    employeeId: 1003,
    name: 'سارا حسینی',
    biometricType: BiometricType::CARD,
    biometricData: '123456789', // شماره کارت (عددی)
    isAdmin: false
);

BiometricDevice::addUser('DEVICE_SERIAL_NUMBER', $addCardUser);
```

**نکته:** برای کارت و پسورد، `biometricData` باید عدد باشد.

### 4. اضافه کردن کاربر با پسورد (Password)

```php
$addPasswordUser = new AddUserDTO(
    employeeId: 1004,
    name: 'حسین کریمی',
    biometricType: BiometricType::PASSWORD,
    biometricData: '1234', // پسورد عددی
    isAdmin: false
);

BiometricDevice::addUser('DEVICE_SERIAL_NUMBER', $addPasswordUser);
```

### 5. اضافه کردن کاربر به عنوان Admin

```php
$addAdminUser = new AddUserDTO(
    employeeId: 9999,
    name: 'مدیر سیستم',
    biometricType: BiometricType::FINGERPRINT_0,
    biometricData: 'BASE64_TEMPLATE',
    isAdmin: true // کاربر Admin
);

BiometricDevice::addUser('DEVICE_SERIAL_NUMBER', $addAdminUser);
```

### 6. حذف کاربر

```php
use Sajadsoft\BiometricDevices\Facades\BiometricDevice;
use Sajadsoft\BiometricDevices\Enums\BiometricType;

// حذف یک بیومتریک خاص
BiometricDevice::deleteUser(
    deviceSerial: 'DEVICE_SERIAL_NUMBER',
    employeeId: 1001,
    biometricType: BiometricType::FINGERPRINT_0
);

// حذف تمام بیومتریک‌های یک کاربر
BiometricDevice::deleteUser(
    deviceSerial: 'DEVICE_SERIAL_NUMBER',
    employeeId: 1001,
    biometricType: null // null = حذف همه
);
```

### 7. دریافت لیست کاربران

```php
// دریافت لیست تمام کاربران از ابتدا
BiometricDevice::getUserList('DEVICE_SERIAL_NUMBER', startFromBeginning: true);

// ادامه دریافت از آخرین نقطه
BiometricDevice::getUserList('DEVICE_SERIAL_NUMBER', startFromBeginning: false);
```

**نتیجه:** رویداد `UserListReceived` trigger می‌شود.

### 8. دریافت اطلاعات یک کاربر خاص

```php
// با استفاده از sendRawCommand
BiometricDevice::sendRawCommand('DEVICE_SERIAL_NUMBER', 'getuserinfo', [
    'cmd' => 'getuserinfo',
    'enrollid' => 1001,
]);
```

**نتیجه:** رویداد `UserInfoReceived` trigger می‌شود.

---

## کنترل دستگاه

### 1. باز کردن درب

```php
// باز کردن درب شماره 1 به مدت 5 ثانیه
BiometricDevice::openDoor('DEVICE_SERIAL_NUMBER', doorNumber: 1);

// با استفاده از sendRawCommand برای تنظیمات دقیق‌تر
BiometricDevice::sendRawCommand('DEVICE_SERIAL_NUMBER', 'opendoor', [
    'cmd' => 'opendoor',
    'doornum' => 1,
    'duration' => 10, // 10 ثانیه
]);
```

### 2. دریافت اطلاعات دستگاه

```php
BiometricDevice::getDeviceInfo('DEVICE_SERIAL_NUMBER');
```

**نتیجه:** رویداد `CommandResponseReceived` با اطلاعات دستگاه trigger می‌شود.

**نمونه پاسخ:**
```php
[
    'serial_number' => 'AYSC01015584',
    'model_name' => 'SpeedFace-V5L',
    'firmware_version' => 'V1.2.3',
    'user_capacity' => 3000,
    'log_capacity' => 100000,
    'used_users' => 150,
    'used_logs' => 5420,
]
```

### 3. راه‌اندازی مجدد دستگاه (Reboot)

```php
BiometricDevice::reboot('DEVICE_SERIAL_NUMBER');
```

**⚠️ هشدار:** دستگاه ریبوت می‌شود و ارتباط برای چند ثانیه قطع می‌شود.

### 4. مقداردهی اولیه سیستم (Factory Reset)

```php
BiometricDevice::initSystem('DEVICE_SERIAL_NUMBER');
```

**⚠️ هشدار خطرناک:** این دستور **تمام داده‌های دستگاه** را پاک می‌کند!

### 5. تنظیم زمان دستگاه

```php
use Carbon\Carbon;

BiometricDevice::sendRawCommand('DEVICE_SERIAL_NUMBER', 'settime', [
    'cmd' => 'settime',
    'time' => Carbon::now()->format('Y-m-d H:i:s'),
]);
```

---

## مدیریت دسترسی

### 1. تنظیم دسترسی زمان‌بندی شده کاربر

```php
use Sajadsoft\BiometricDevices\DTOs\Commands\SetUserAccessDTO;
use Carbon\Carbon;

$setAccess = new SetUserAccessDTO(
    employeeId: 1001,
    weekZone: 1,     // شماره zone زمانی هفتگی
    group: 1,        // شماره گروه
    startDate: Carbon::parse('2025-01-01'),
    endDate: Carbon::parse('2025-12-31')
);

BiometricDevice::setUserAccess('DEVICE_SERIAL_NUMBER', $setAccess);
```

**نکات:**
- `weekZone`: تعیین می‌کند کاربر در چه روزها و ساعاتی می‌تواند تردد کند
- `group`: گروه دسترسی کاربر
- این تنظیمات معمولاً در دستگاه از قبل پیکربندی شده‌اند

### 2. قفل کردن/باز کردن دستگاه

```php
// قفل کردن دستگاه
BiometricDevice::sendRawCommand('DEVICE_SERIAL_NUMBER', 'setdevlock', [
    'cmd' => 'setdevlock',
    'locked' => 1, // 1 = قفل، 0 = باز
]);

// باز کردن قفل
BiometricDevice::sendRawCommand('DEVICE_SERIAL_NUMBER', 'setdevlock', [
    'cmd' => 'setdevlock',
    'locked' => 0,
]);
```

### 3. قفل کردن/باز کردن کاربر

```php
// قفل کردن کاربر خاص
BiometricDevice::sendRawCommand('DEVICE_SERIAL_NUMBER', 'setuserlock', [
    'cmd' => 'setuserlock',
    'count' => 1,
    'record' => [
        [
            'enrollid' => 1001,
            'locked' => 1, // 1 = قفل
        ]
    ],
]);
```

---

## مدیریت لاگ‌های حضور

### 1. دریافت تمام لاگ‌ها

```php
BiometricDevice::sendRawCommand('DEVICE_SERIAL_NUMBER', 'getalllog', [
    'cmd' => 'getalllog',
    'stn' => 1, // 1 = از ابتدا، 0 = ادامه
]);
```

**نتیجه:** رویداد `AttendanceReceived` برای هر رکورد trigger می‌شود.

### 2. دریافت لاگ‌های جدید

```php
BiometricDevice::sendRawCommand('DEVICE_SERIAL_NUMBER', 'getnewlog', [
    'cmd' => 'getnewlog',
    'stn' => 1,
]);
```

**تفاوت با `getalllog`:** فقط لاگ‌هایی که هنوز ارسال نشده‌اند را برمی‌گرداند.

### 3. دریافت خودکار لاگ‌ها (Real-time)

دستگاه‌ها به صورت خودکار بعد از هر تردد، لاگ را به سرور می‌فرستند:

```php
// نیازی به کد خاصی نیست!
// فقط به Event گوش دهید:
```

---

## رویدادها

### 1. گوش دادن به حضور و غیاب (AttendanceReceived)

```php
// app/Listeners/SaveAttendanceToDatabase.php

namespace App\Listeners;

use Sajadsoft\BiometricDevices\Events\AttendanceReceived;
use App\Models\Attendance;

class SaveAttendanceToDatabase
{
    public function handle(AttendanceReceived $event): void
    {
        $dto = $event->attendance;
        
        Attendance::create([
            'employee_id' => $dto->employeeId,
            'employee_name' => $dto->employeeName,
            'device_serial' => $dto->deviceSerial,
            'check_time' => $dto->timestamp,
            'is_check_in' => $dto->isCheckIn,
            'verification_type' => $dto->verificationType->value,
            'event_type' => $dto->eventType?->value,
            'photo' => $dto->photoBase64,
        ]);
        
        \Log::info('Attendance saved', [
            'employee_id' => $dto->employeeId,
            'time' => $dto->timestamp,
        ]);
    }
}
```

**خصوصیات AttendanceDTO:**
```php
$dto->employeeId;           // int: شناسه کارمند
$dto->employeeName;         // string: نام کارمند
$dto->timestamp;            // Carbon: زمان تردد
$dto->verificationType;     // VerificationMode enum
$dto->isCheckIn;            // bool: ورود یا خروج
$dto->deviceSerial;         // string: شماره سریال دستگاه
$dto->photoBase64;          // ?string: عکس (Base64)
$dto->eventType;            // ?AttendanceEventType enum
$dto->rawData;              // array: داده‌های خام دستگاه
```

### 2. گوش دادن به لیست کاربران (UserListReceived)

```php
// app/Listeners/SyncUsersToDatabase.php

namespace App\Listeners;

use Sajadsoft\BiometricDevices\Events\UserListReceived;

class SyncUsersToDatabase
{
    public function handle(UserListReceived $event): void
    {
        foreach ($event->users as $userDTO) {
            // هر userDTO یک EnrollmentDTO است
            \Log::info('User enrollment', [
                'employee_id' => $userDTO->employeeId,
                'biometric_type' => $userDTO->biometricType->label(),
                'is_admin' => $userDTO->isAdmin,
            ]);
            
            // ذخیره یا آپدیت در دیتابیس
            // ...
        }
    }
}
```

### 3. گوش دادن به اطلاعات کاربر (UserInfoReceived)

```php
// app/Listeners/SaveUserBiometric.php

namespace App\Listeners;

use Sajadsoft\BiometricDevices\Events\UserInfoReceived;
use App\Models\UserBiometric;

class SaveUserBiometric
{
    public function handle(UserInfoReceived $event): void
    {
        $dto = $event->user;
        
        UserBiometric::updateOrCreate(
            [
                'employee_id' => $dto->employeeId,
                'biometric_type' => $dto->biometricType->value,
            ],
            [
                'name' => $dto->name,
                'biometric_data' => $dto->biometricData,
                'card_number' => $dto->cardNumber,
                'password' => $dto->password,
                'is_admin' => $dto->isAdmin,
                'enabled' => $dto->enabled,
            ]
        );
    }
}
```

### 4. گوش دادن به QR Code (QrCodeReceived)

```php
// app/Listeners/ProcessQrCodeScan.php

namespace App\Listeners;

use Sajadsoft\BiometricDevices\Events\QrCodeReceived;

class ProcessQrCodeScan
{
    public function handle(QrCodeReceived $event): void
    {
        $qrCode = $event->qrCode;
        
        \Log::info('QR Code scanned', [
            'device' => $qrCode->deviceSerial,
            'content' => $qrCode->qrCodeData,
            'employee_id' => $qrCode->employeeId,
            'timestamp' => $qrCode->timestamp,
        ]);
        
        // پردازش QR Code (مثلاً باز کردن درب)
        if ($this->isValidQrCode($qrCode->qrCodeData)) {
            BiometricDevice::openDoor($qrCode->deviceSerial);
        }
    }
}
```

### 5. گوش دادن به اتصال دستگاه (DeviceConnected)

```php
// app/Listeners/NotifyDeviceConnected.php

namespace App\Listeners;

use Sajadsoft\BiometricDevices\Events\DeviceConnected;

class NotifyDeviceConnected
{
    public function handle(DeviceConnected $event): void
    {
        // دستگاه قبلاً توسط پکیج ذخیره شده است
        $device = $event->device;
        $deviceInfo = $event->deviceInfo;
        
        \Log::info('Device connected', [
            'serial' => $event->deviceSerial,
            'model' => $deviceInfo->modelName,
            'firmware' => $deviceInfo->firmwareVersion,
            'users' => $deviceInfo->usedUsers . '/' . $deviceInfo->userCapacity,
        ]);
        
        // ارسال نوتیفیکیشن به مدیران
        // ...
    }
}
```

### 6. گوش دادن به قطع دستگاه (DeviceDisconnected)

```php
// app/Listeners/NotifyDeviceDisconnected.php

namespace App\Listeners;

use Sajadsoft\BiometricDevices\Events\DeviceDisconnected;

class NotifyDeviceDisconnected
{
    public function handle(DeviceDisconnected $event): void
    {
        \Log::warning('Device disconnected', [
            'serial' => $event->deviceSerial,
            'disconnected_at' => $event->disconnectedAt,
        ]);
        
        // ارسال هشدار
        // ...
    }
}
```

### 7. گوش دادن به ارسال دستور (CommandSent)

```php
// app/Listeners/LogCommandSent.php

namespace App\Listeners;

use Sajadsoft\BiometricDevices\Events\CommandSent;

class LogCommandSent
{
    public function handle(CommandSent $event): void
    {
        \Log::info('Command sent', [
            'device' => $event->deviceSerial,
            'command' => $event->commandName,
            'command_id' => $event->command?->id,
        ]);
    }
}
```

### 8. گوش دادن به پاسخ دستور (CommandResponseReceived)

```php
// app/Listeners/HandleCommandResponse.php

namespace App\Listeners;

use Sajadsoft\BiometricDevices\Events\CommandResponseReceived;

class HandleCommandResponse
{
    public function handle(CommandResponseReceived $event): void
    {
        if ($event->success) {
            \Log::info('Command succeeded', [
                'device' => $event->deviceSerial,
                'command' => $event->commandName,
            ]);
        } else {
            \Log::error('Command failed', [
                'device' => $event->deviceSerial,
                'command' => $event->commandName,
                'response' => $event->responseData,
            ]);
            
            // ارسال نوتیفیکیشن خطا
        }
    }
}
```

---

## استفاده پیشرفته

### 1. ارسال دستور سفارشی (Raw Command)

برای دستوراتی که متد مخصوص ندارند:

```php
use Sajadsoft\BiometricDevices\Facades\BiometricDevice;

// تنظیم نام کاربر
BiometricDevice::sendRawCommand('DEVICE_SERIAL', 'setusername', [
    'cmd' => 'setusername',
    'enrollid' => 1001,
    'name' => 'نام جدید',
]);

// پاک کردن ادمین‌ها
BiometricDevice::sendRawCommand('DEVICE_SERIAL', 'cleanadmin', [
    'cmd' => 'cleanadmin',
]);

// دریافت وضعیت قفل دستگاه
BiometricDevice::sendRawCommand('DEVICE_SERIAL', 'getdevlock', [
    'cmd' => 'getdevlock',
]);
```

### 2. استفاده از چند دستگاه مختلف همزمان

```php
use Sajadsoft\BiometricDevices\Models\Device;
use Sajadsoft\BiometricDevices\Services\MapperFactory;

// دریافت دستگاه
$device = Device::where('serial', 'DEVICE_SERIAL')->first();

// ایجاد mapper مخصوص آن دستگاه
$mapper = MapperFactory::create(
    $device->type->value,  // 'aiface' یا 'zkteco'
    'websocket'
);

// تبدیل داده‌های خام به DTO
$attendanceDTO = $mapper->mapToAttendanceDTO($rawData);
```

### 3. ارسال دستور به تمام دستگاه‌های آنلاین

```php
use Sajadsoft\BiometricDevices\Models\Device;
use Sajadsoft\BiometricDevices\Facades\BiometricDevice;

$onlineDevices = Device::online()->get();

foreach ($onlineDevices as $device) {
    BiometricDevice::getDeviceInfo($device->serial);
}
```

### 4. Retry دستورات ناموفق

```php
use Sajadsoft\BiometricDevices\Models\DeviceCommand;
use Sajadsoft\BiometricDevices\Enums\DeviceCommandStatusEnum;

$failedCommands = DeviceCommand::where('status', DeviceCommandStatusEnum::FAILED)
    ->where('error_count', '<', 3)
    ->get();

foreach ($failedCommands as $command) {
    // تبدیل به pending برای ارسال مجدد
    $command->update([
        'status' => DeviceCommandStatusEnum::PENDING,
        'send_status' => false,
    ]);
}
```

### 5. استفاده با Queue Jobs

```php
// app/Jobs/AddUserToDevice.php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Sajadsoft\BiometricDevices\Facades\BiometricDevice;
use Sajadsoft\BiometricDevices\DTOs\Commands\AddUserDTO;
use Sajadsoft\BiometricDevices\Enums\BiometricType;

class AddUserToDevice implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $deviceSerial,
        public int $employeeId,
        public string $name,
        public string $biometricData,
    ) {}

    public function handle(): void
    {
        $dto = new AddUserDTO(
            employeeId: $this->employeeId,
            name: $this->name,
            biometricType: BiometricType::FACE_0,
            biometricData: $this->biometricData,
            isAdmin: false
        );

        BiometricDevice::addUser($this->deviceSerial, $dto);
    }
}

// استفاده:
AddUserToDevice::dispatch('DEVICE_SERIAL', 1001, 'علی احمدی', 'BASE64_DATA');
```

### 6. بررسی وضعیت دستورات

```php
use Sajadsoft\BiometricDevices\Models\Device;
use Sajadsoft\BiometricDevices\Models\DeviceCommand;

$device = Device::where('serial', 'DEVICE_SERIAL')->first();

// دستورات pending
$pending = $device->pendingCommands;
echo "Pending: " . $pending->count();

// دستورات موفق امروز
$successToday = $device->successCommands()
    ->whereDate('created_at', today())
    ->count();

// دستورات ناموفق
$failed = $device->failedCommands;
foreach ($failed as $command) {
    echo "Failed: {$command->command_name} - {$command->error_message}\n";
}
```

### 7. Custom Events برای دستورات خاص

```php
// app/Events/UserAddedToDevice.php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserAddedToDevice
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $employeeId,
        public string $deviceSerial,
        public bool $success,
    ) {}
}

// در Listener:
use Sajadsoft\BiometricDevices\Events\CommandResponseReceived;

class HandleUserAddResponse
{
    public function handle(CommandResponseReceived $event): void
    {
        if ($event->commandName === 'setuserinfo') {
            event(new UserAddedToDevice(
                employeeId: $event->responseData['enrollid'],
                deviceSerial: $event->deviceSerial,
                success: $event->success,
            ));
        }
    }
}
```

---

## نکات مهم و Best Practices

### ✅ Do's

1. **همیشه بررسی کنید دستگاه آنلاین است:**
```php
$device = Device::where('serial', $serial)->first();
if ($device && $device->isOnline()) {
    BiometricDevice::getUserList($serial);
}
```

2. **از Queue استفاده کنید برای عملیات سنگین:**
```php
AddMultipleUsersJob::dispatch($deviceSerial, $users);
```

3. **خطاها را log کنید:**
```php
try {
    BiometricDevice::addUser($serial, $dto);
} catch (\Exception $e) {
    \Log::error('Failed to add user', [
        'employee_id' => $dto->employeeId,
        'error' => $e->getMessage(),
    ]);
}
```

### ❌ Don'ts

1. **مستقیم به دستگاه درخواست زیاد نفرستید:**
```php
// ❌ Bad
for ($i = 0; $i < 1000; $i++) {
    BiometricDevice::addUser($serial, $user);
}

// ✅ Good
foreach (array_chunk($users, 10) as $batch) {
    AddUsersBatchJob::dispatch($serial, $batch);
    sleep(1); // تاخیر بین batch ها
}
```

2. **فراموش نکنید که Event Listener بنویسید:**
```php
// دستورات ارسال می‌شوند اما نتیجه ذخیره نمی‌شود!
```

3. **دستورات خطرناک را بدون تایید اجرا نکنید:**
```php
// ❌ خطرناک
BiometricDevice::initSystem($serial); // همه داده‌ها پاک می‌شود!
```

---

## عیب‌یابی (Troubleshooting)

### مشکل: دستگاه connect نمی‌شود

**راه حل:**
```bash
# بررسی لاگ
tail -f storage/logs/laravel.log

# بررسی پورت
netstat -an | grep 7788

# بررسی IP
ping DEVICE_IP
```

### مشکل: دستورات ارسال نمی‌شوند

**بررسی:**
```php
use Sajadsoft\BiometricDevices\Models\DeviceCommand;

$pending = DeviceCommand::pending()->get();
// اگر دستورات pending وجود دارند اما ارسال نمی‌شوند:
// 1. بررسی کنید سرور WebSocket در حال اجراست
// 2. بررسی کنید دستگاه آنلاین است
```

### مشکل: Events trigger نمی‌شوند

**راه حل:**
```php
// Laravel 11: مطمئن شوید Listener ها type-hint دارند
public function handle(AttendanceReceived $event) // ✅

// یا در EventServiceProvider ثبت کنید
```

---

## منابع بیشتر

- [README اصلی](../README.md)
- [نمونه‌های پیشرفته](EXAMPLES.md)
- [مستندات API](API.md)
- [Changelog](../CHANGELOG.md)

---

**توسعه دهنده:** با ❤️ برای جامعه Laravel  
**نسخه:** 1.0.0  
**تاریخ بروزرسانی:** 2025-01-28

