# پکیج دستگاه‌های بایومتریک لاراول

[![Latest Version](https://img.shields.io/packagist/v/sajadsoft1/biometric-device.svg?style=flat-square)](https://packagist.org/packages/sajadsoft1/biometric-device)
[![Total Downloads](https://img.shields.io/packagist/dt/sajadsoft1/biometric-device.svg?style=flat-square)](https://packagist.org/packages/sajadsoft1/biometric-device)
[![License](https://img.shields.io/packagist/l/sajadsoft1/biometric-device.svg?style=flat-square)](https://packagist.org/packages/sajadsoft1/biometric-device)

پکیج رویداد محور لاراول برای ارتباط یکپارچه با دستگاه‌های بایومتریک (اسکنر اثر انگشت، تشخیص چهره، سیستم‌های کنترل تردد).

## ویژگی‌ها

- **معماری رویداد محور** - کاملاً Event-driven
- **پشتیبانی از DTO** - اشیاء انتقال داده استاندارد برای استقلال از دستگاه
- **پشتیبانی از پروتکل‌های متعدد** - WebSocket، TCP/IP، HTTP، MQTT (قابل توسعه)
- **معماری تمیز** - پترن‌های Strategy، Pipeline و Handler
- **مدیریت دستورات** - ردیابی خودکار دستورات در دیتابیس
- **مستندسازی کامل** - مستندات جامع به فارسی و انگلیسی
- **آماده برای Laravel 11/12** - ساخته شده برای لاراول مدرن

## دستگاه‌های پشتیبانی شده

- دستگاه‌های WebSocket زمان و حضور AIFace (AiFace، FaceLite، SpeedFace و غیره)
- دستگاه‌های WebSocket زمان و حضور ZKTeco (سازگار با AIFace)
- دستگاه‌های TCP/IP زمان و حضور (به زودی)
- دستگاه‌های سفارشی (از طریق رابط DataMapper)
- **پشتیبانی همزمان از چند نوع دستگاه** - می‌توانید به طور همزمان از انواع مختلف دستگاه‌ها استفاده کنید

## نصب

```bash
composer require sajadsoft1/biometric-device
```

### انتشار فایل‌های پکیج

```bash
# انتشار فایل پیکربندی
php artisan vendor:publish --tag=biometric-devices-config

# انتشار migrations (اختیاری - به صورت خودکار load می‌شوند)
php artisan vendor:publish --tag=biometric-devices-migrations

# اجرای migrations
php artisan migrate
```

**نکات مهم:**
- Migration ها به صورت خودکار load می‌شوند، نیازی به publish نیست
- Migration های پکیج با تاریخ `2020_01_01` شروع می‌شوند تا همیشه **قبل** از migration های پروژه شما اجرا شوند
- در پروژه‌های قدیمی، migration ها تداخلی ایجاد نمی‌کنند

این دستورات فایل‌های زیر را ایجاد می‌کنند:
- `config/biometric-devices.php` - تنظیمات پکیج

Migration های خودکار (Auto-load):
- `2020_01_01_000001_create_devices_table.php` - جدول دستگاه‌ها
- `2020_01_01_000002_create_device_commands_table.php` - جدول دستورات

## 🚀 شروع سریع

### مراحل نصب و راه‌اندازی در 5 دقیقه

#### ۱. نصب پکیج

```bash
composer require sajadsoft1/biometric-device
php artisan vendor:publish --tag=biometric-devices-config
php artisan migrate
```

#### ۲. پیکربندی (.env)

```env
BIOMETRIC_WS_HOST=0.0.0.0
BIOMETRIC_WS_PORT=7788
BIOMETRIC_DRIVER=websocket
BIOMETRIC_DEVICE_TYPE=aiface
```

#### ۳. راه‌اندازی سرور

```bash
php artisan biometric:start-server
```

#### ۴. گوش دادن به رویدادها

**🔔 نکته مهم (Laravel 11+):**  
در **Laravel 11 و بالاتر**، Listener ها به صورت **خودکار** کشف می‌شوند (Auto-Discovery). اگر متد Listener شما type-hint داشته باشد، **نیازی به ثبت دستی در `AppServiceProvider` نیست**:

```php
// app/Listeners/SaveAttendance.php
// ✅ به صورت خودکار ثبت می‌شود

use Sajadsoft\BiometricDevices\Events\AttendanceReceived;

class SaveAttendance
{
    // Laravel به طور خودکار این متد را به AttendanceReceived وصل می‌کند
    public function handle(AttendanceReceived $event)
    {
        // ...
    }
}
```

**برای غیرفعال کردن Auto-Discovery و ثبت دستی:**

```php
// app/Providers/AppServiceProvider.php

public function boot(): void
{
    // غیرفعال کردن Auto-Discovery
    Event::shouldDiscoverEvents(false);
    
    // ثبت دستی Listener ها
    Event::listen(
        AttendanceReceived::class,
        \App\Listeners\SaveAttendance::class,
    );
}
```

**برای Laravel 10 و قبل‌تر** (ثبت دستی در EventServiceProvider):

```php
// app/Providers/EventServiceProvider.php

protected $listen = [
    AttendanceReceived::class => [
        \App\Listeners\SaveAttendance::class,
    ],
    UserListReceived::class => [
        \App\Listeners\SyncUsers::class,
    ],
];
```

### ۴. مدیریت رویدادها

```php
// app/Listeners/SaveAttendance.php

use Sajadsoft\BiometricDevices\Events\AttendanceReceived;

class SaveAttendance
{
    public function handle(AttendanceReceived $event)
    {
        $dto = $event->attendance; // AttendanceDTO
        
        // ذخیره در دیتابیس
        AttendanceLog::create([
            'employee_id' => $dto->employeeId,
            'employee_name' => $dto->employeeName,
            'check_time' => $dto->timestamp,
            'is_check_in' => $dto->isCheckIn,
            'verification' => $dto->verificationType->label(),
            'temperature' => $dto->temperature,
        ]);
    }
}
```

### ۵. ارسال دستورات از طریق Facade

```php
use Sajadsoft\BiometricDevices\Facades\BiometricDevice;
use Sajadsoft\BiometricDevices\DTOs\Commands\AddUserDTO;
use Sajadsoft\BiometricDevices\DTOs\Commands\OpenDoorDTO;
use Sajadsoft\BiometricDevices\Enums\BiometricType;

// دریافت لیست کاربران
BiometricDevice::getUserList('DEVICE_SERIAL');

// افزودن کاربر به دستگاه
$addUser = new AddUserDTO(
    employeeId: 100,
    name: 'علی احمدی',
    biometricType: BiometricType::FINGERPRINT_0,
    biometricData: 'base64_template...',
    isAdmin: false
);

BiometricDevice::addUser('DEVICE_SERIAL', $addUser);

// باز کردن درب
BiometricDevice::openDoor('DEVICE_SERIAL', new OpenDoorDTO(doorNumber: 1));

// دریافت اطلاعات دستگاه
BiometricDevice::getDeviceInfo('DEVICE_SERIAL');
```

**نکات مهم:**
- ✅ **ذخیره خودکار**: پکیج خودش دستورات را در جدول `device_commands` ذخیره می‌کند
- ✅ **تغییر وضعیت خودکار**: وضعیت دستورات (PENDING → SENT → SUCCESS/FAILED) خودکار تغییر می‌کند
- ✅ **ارسال خودکار**: سرور WebSocket هر ۱ ثانیه دستورات pending را بررسی و ارسال می‌کند
- ✅ **Event ها**: همچنان برای اطلاع‌رسانی و پردازش‌های اضافی فایر می‌شوند
- ℹ️ **Listener ها اختیاری هستند**: نیازی به نوشتن Listener برای ذخیره/آپدیت دستورات نیست

## معماری

### جریان دریافت رویداد

```
Device → Driver → Pipeline → Handler → Mapper → DTO → Event → Your Listener
```

### جریان ارسال دستور

```
Your Code → Facade → Manager → Mapper → DTO → Command → Driver → Device
```

## مدیریت خودکار دستگاه‌ها

### 🎯 ذخیره و مدیریت خودکار

پکیج **به صورت خودکار** دستگاه‌ها را در دیتابیس ذخیره و وضعیت آنلاین/آفلاین را کنترل می‌کند:

#### 🟢 اتصال دستگاه (Automatic)
```
1. دستگاه به سرور WebSocket متصل می‌شود
   ↓
2. پکیج اطلاعات دستگاه را دریافت می‌کند
   ↓
3. Device::updateOrCreate() ← ذخیره خودکار
   - serial
   - name
   - ip_address
   - port
   - is_online = true
   - last_connected_at
   - extra_attributes (firmware, capacity, etc.)
   ↓
4. event(new DeviceConnected()) ← اطلاع‌رسانی
   - شامل Device model
   - شامل DeviceInfoDTO
```

#### 🔴 قطع دستگاه (Automatic)
```
1. دستگاه از سرور قطع می‌شود
   ↓
2. Device::markAsOffline() ← بروزرسانی خودکار
   - is_online = false
   - last_disconnected_at
   ↓
3. event(new DeviceDisconnected()) ← اطلاع‌رسانی
```

### 📝 Listener های شما (اختیاری)

با توجه به اینکه پکیج خودش Device را ذخیره می‌کند، Listener های شما فقط برای:
- اضافه کردن فیلدهای سفارشی (مثل `club_id`, `device_type`)
- ارسال نوتیفیکیشن
- پردازش‌های اضافی کسب و کار

```php
// app/Listeners/UpdateDeviceStatus.php

class UpdateDeviceStatus
{
    public function handleConnected(DeviceConnected $event): void
    {
        // پکیج قبلاً Device را ذخیره کرده است
        if ($event->device) {
            // فقط فیلدهای سفارشی پروژه را بروزرسانی کنید
            $event->device->update([
                'device_type' => DeviceTypeEnum::AI_FACE,
                'club_id' => auth()->user()->club_id,
            ]);
        }
    }
    
    public function handleDisconnected(DeviceDisconnected $event): void
    {
        // پکیج قبلاً وضعیت offline را ذخیره کرده است
        // در صورت نیاز، پردازش اضافی انجام دهید
        Notification::send(
            User::admins(),
            new DeviceOfflineNotification($event->deviceSerial)
        );
    }
}
```

### 🔍 استفاده از Device Model

```php
use Sajadsoft\BiometricDevices\Models\Device;

// دریافت دستگاه‌های آنلاین
$onlineDevices = Device::online()->get();

// دریافت دستگاه‌های آفلاین
$offlineDevices = Device::offline()->get();

// بررسی وضعیت
$device = Device::where('serial', 'AYSC01015584')->first();
if ($device->isOnline()) {
    // ارسال دستور
    BiometricDevice::getUserList($device->serial);
}

// دسترسی به دستورات
$pendingCommands = $device->pendingCommands;
$failedCommands = $device->failedCommands;
```

---

## مدیریت خودکار دستورات

### 🎯 ذخیره و تغییر وضعیت خودکار

پکیج **به صورت کامل خودکار** دستورات را مدیریت می‌کند:

#### 1️⃣ ارسال دستور (Automatic Save)
```php
BiometricDevice::getUserList('AYSC01015584');
```

```
1. BiometricDeviceManager::sendRawCommand()
   ↓
2. ✅ DeviceCommand::create() ← ذخیره خودکار توسط پکیج
   - device_id
   - command_name = 'getuserlist'
   - command_content = {...}
   - status = PENDING
   - send_status = false
   ↓
3. event(new CommandSent(..., $command)) ← اطلاع‌رسانی
```

#### 2️⃣ ارسال به دستگاه (Automatic Status: SENT)
```
WebSocketDeviceDriver::checkPendingCommands() (هر 1 ثانیه)
   ↓
✅ $command->markAsSent() ← تغییر خودکار توسط پکیج
   - status = SENT
   - send_status = true
```

#### 3️⃣ دریافت پاسخ (Automatic Status: SUCCESS/FAILED)
```
Handler::handle($data)
   ↓
✅ $this->updateCommandStatus(...) ← تغییر خودکار توسط پکیج
   - status = SUCCESS یا FAILED
   - executed_at = now()
   - response = {...}
   ↓
event(new CommandResponseReceived(...)) ← اطلاع‌رسانی
```

### 📝 Listener های شما (اختیاری)

با توجه به اینکه پکیج خودش دستورات را ذخیره و مدیریت می‌کند، Listener های شما فقط برای:
- ارسال نوتیفیکیشن
- لاگ گرفتن اضافی
- پردازش‌های خاص کسب و کار

```php
// app/Listeners/NotifyOnCommandFailed.php

class NotifyOnCommandFailed
{
    public function handle(CommandResponseReceived $event): void
    {
        // پکیج قبلاً وضعیت را بروزرسانی کرده است
        
        if (!$event->success) {
            // ارسال نوتیفیکیشن
            Notification::send(
                User::admins(),
                new CommandFailedNotification($event->deviceSerial, $event->commandName)
            );
        }
    }
}
```

### 🔍 Query دستورات

```php
use Sajadsoft\BiometricDevices\Models\DeviceCommand;

// دستورات pending
$pending = DeviceCommand::pending()->get();

// دستورات ناموفق
$failed = DeviceCommand::failed()
    ->where('created_at', '>=', now()->subDay())
    ->get();

// دستورات یک دستگاه خاص
$device = Device::where('serial', 'AYSC01015584')->first();
$commands = $device->commands()
    ->where('command_name', 'getUserList')
    ->latest()
    ->get();
```

## 📋 راهنمای دستورات (Commands Reference)

### دستورات موجود و متدهای Facade

| دستور | متد Facade | پارامترها | توضیحات | مستندات |
|-------|------------|-----------|---------|---------|
| **مدیریت کاربران** |
| `setuserinfo` | `addUser($serial, AddUserDTO)` | employeeId, name, biometricType, biometricData, isAdmin | افزودن/ویرایش کاربر | [📖](docs/USAGE.md#1-اضافه-کردن-کاربر-با-اثر-انگشت-fingerprint) |
| `deleteuser` | `deleteUser($serial, $employeeId, ?$type)` | employeeId, biometricType (optional) | حذف کاربر یا بیومتریک خاص | [📖](docs/USAGE.md#6-حذف-کاربر) |
| `getuserlist` | `getUserList($serial, $startFromBeginning)` | startFromBeginning (bool) | دریافت لیست کاربران | [📖](docs/USAGE.md#7-دریافت-لیست-کاربران) |
| `getuserinfo` | `sendRawCommand($serial, 'getuserinfo', [...])` | enrollid | دریافت اطلاعات کاربر با بیومتریک | [📖](docs/USAGE.md#8-دریافت-اطلاعات-یک-کاربر-خاص) |
| `senduser` | - | - | دستگاه خودش ارسال می‌کند | - |
| **کنترل دستگاه** |
| `opendoor` | `openDoor($serial, $doorNumber)` | doorNumber, duration | باز کردن درب | [📖](docs/USAGE.md#1-باز-کردن-درب) |
| `getdevinfo` | `getDeviceInfo($serial)` | - | دریافت اطلاعات دستگاه | [📖](docs/USAGE.md#2-دریافت-اطلاعات-دستگاه) |
| `reboot` | `reboot($serial)` | - | راه‌اندازی مجدد دستگاه | [📖](docs/USAGE.md#3-راهاندازی-مجدد-دستگاه-reboot) |
| `initsys` | `initSystem($serial)` | - | ⚠️ پاک کردن کل داده‌های دستگاه | [📖](docs/USAGE.md#4-مقداردهی-اولیه-سیستم-factory-reset) |
| `settime` | `sendRawCommand($serial, 'settime', [...])` | time | تنظیم زمان دستگاه | [📖](docs/USAGE.md#5-تنظیم-زمان-دستگاه) |
| **کنترل دسترسی** |
| `setuserlock` | `setUserAccess($serial, SetUserAccessDTO)` | employeeId, weekZone, group, startDate, endDate | تنظیم دسترسی زمان‌بندی شده | [📖](docs/USAGE.md#1-تنظیم-دسترسی-زمانبندی-شده-کاربر) |
| `setdevlock` | `sendRawCommand($serial, 'setdevlock', [...])` | locked (bool) | قفل/باز کردن دستگاه | [📖](docs/USAGE.md#2-قفل-کردنباز-کردن-دستگاه) |
| `getuserlock` | `sendRawCommand($serial, 'getuserlock', [...])` | enrollid | دریافت وضعیت قفل کاربر | - |
| `getdevlock` | `sendRawCommand($serial, 'getdevlock', [...])` | - | دریافت وضعیت قفل دستگاه | - |
| **مدیریت لاگ‌ها** |
| `sendlog` | - | - | دستگاه خودش ارسال می‌کند | - |
| `getalllog` | `sendRawCommand($serial, 'getalllog', [...])` | stn | دریافت تمام لاگ‌ها | [📖](docs/USAGE.md#1-دریافت-تمام-لاگها) |
| `getnewlog` | `sendRawCommand($serial, 'getnewlog', [...])` | stn | دریافت لاگ‌های جدید | [📖](docs/USAGE.md#2-دریافت-لاگهای-جدید) |
| **سایر** |
| `reg` | - | - | ثبت دستگاه (خودکار) | - |
| `sendqrcode` | - | - | دستگاه خودش ارسال می‌کند | - |
| `setusername` | `sendRawCommand($serial, 'setusername', [...])` | enrollid, name | تغییر نام کاربر | [📖](docs/EXAMPLES.md#1-ارسال-دستور-سفارشی-raw-command) |
| `cleanadmin` | `sendRawCommand($serial, 'cleanadmin', [...])` | - | پاک کردن تمام ادمین‌ها | [📖](docs/EXAMPLES.md#1-ارسال-دستور-سفارشی-raw-command) |

### نکات مهم

- ✅ **دستورات خودکار:** `reg`, `senduser`, `sendlog`, `sendqrcode` توسط دستگاه خودش ارسال می‌شوند
- ✅ **ذخیره خودکار:** تمام دستورات به صورت خودکار در جدول `device_commands` ذخیره می‌شوند
- ✅ **وضعیت خودکار:** وضعیت دستورات (PENDING → SENT → SUCCESS/FAILED) به صورت خودکار تغییر می‌کند
- ⚠️ **دستورات خطرناک:** `initsys` و `cleanadmin` باید با احتیاط استفاده شوند

### نمونه استفاده

```php
use Sajadsoft\BiometricDevices\Facades\BiometricDevice;
use Sajadsoft\BiometricDevices\DTOs\Commands\AddUserDTO;
use Sajadsoft\BiometricDevices\Enums\BiometricType;

// اضافه کردن کاربر
$dto = new AddUserDTO(
    employeeId: 1001,
    name: 'علی احمدی',
    biometricType: BiometricType::FACE_0,
    biometricData: 'BASE64_TEMPLATE',
    isAdmin: false
);
BiometricDevice::addUser('DEVICE_SERIAL', $dto);

// باز کردن درب
BiometricDevice::openDoor('DEVICE_SERIAL', doorNumber: 1);

// دریافت اطلاعات دستگاه
BiometricDevice::getDeviceInfo('DEVICE_SERIAL');
```

برای نمونه‌های بیشتر، [راهنمای کامل استفاده](docs/USAGE.md) را مطالعه کنید.

---

## رویدادهای موجود

| رویداد | Properties | توضیحات |
|-------|-----|-------------|
| `DeviceConnected` | `$deviceSerial`, `$deviceInfo` (DTO), `$device` (Model) | دستگاه به سرور متصل شد + Device خودکار ذخیره شده |
| `DeviceDisconnected` | `$deviceSerial`, `$disconnectedAt` | دستگاه قطع شد + وضعیت offline خودکار ذخیره شده |
| `AttendanceReceived` | `$attendance` (DTO) | حضور/تردد دریافت شد |
| `QrCodeReceived` | `$qrCode` (DTO) | QR code اسکن شد (برای دستگاه‌هایی که از QR/Barcode پشتیبانی می‌کنند) |
| `UserListReceived` | `$deviceSerial`, `$users` (DTO[]) | لیست کاربران دریافت شد |
| `UserInfoReceived` | `$deviceSerial`, `$user` (DTO) | اطلاعات کاربر به همراه داده‌های بایومتریک ⚠️ فقط زمانی که command واقعی ارسال شده باشد |
| `CommandSent` | `$deviceSerial`, `$commandName`, `$dto`, `$command` (Model) | دستور ارسال شد + Command خودکار ذخیره شده |
| `CommandResponseReceived` | `$deviceSerial`, `$commandName`, `$success`, `$data` | پاسخ دریافت شد + وضعیت خودکار بروزرسانی شده |

> **نکته:** دستگاه‌های ZKTeco به صورت دوره‌ای اطلاعات کاربران را ارسال می‌کنند، اما `UserInfoReceived` فقط زمانی trigger می‌شود که شما واقعاً command `getUserInfo()` را ارسال کرده باشید. این رفتار از لاگ‌های بی‌مورد جلوگیری می‌کند.

## مدل‌ها

### Device Model

پکیج شامل مدل `Device` پایه است که به صورت خودکار دستگاه‌ها را مدیریت می‌کند:

```php
use Sajadsoft\BiometricDevices\Models\Device;

// Scopes
Device::online()->get();  // دستگاه‌های آنلاین
Device::offline()->get(); // دستگاه‌های آفلاین

// Methods
$device->isOnline();      // bool
$device->markAsOnline();  // علامت‌گذاری به عنوان آنلاین
$device->markAsOffline(); // علامت‌گذاری به عنوان آفلاین
$device->updateDeviceInfo(['key' => 'value']); // بروزرسانی extra_attributes

// Relationships
$device->commands;         // تمام دستورات
$device->pendingCommands;  // دستورات در انتظار
$device->sentCommands;     // دستورات ارسال شده
$device->successCommands;  // دستورات موفق
$device->failedCommands;   // دستورات ناموفق
```

**ستون‌های جدول devices:**

| ستون | نوع | توضیح |
|------|-----|-------|
| serial | string | شماره سریال دستگاه (unique) |
| name | string | نام دستگاه |
| ip_address | string | آدرس IP |
| port | integer | شماره پورت |
| is_online | boolean | وضعیت آنلاین/آفلاین |
| last_connected_at | datetime | آخرین زمان اتصال |
| last_disconnected_at | datetime | آخرین زمان قطع |
| extra_attributes | json | اطلاعات اضافی (firmware، capacity، ...) |

**نکته:** می‌توانید این مدل را extend کنید و فیلدهای سفارشی اضافه کنید. [راهنمای Extend کردن Models](EXTENDING-MODELS.md)

### DeviceCommand Model

پکیج شامل مدل `DeviceCommand` برای ردیابی دستورات است:

```php
use Sajadsoft\BiometricDevices\Models\DeviceCommand;

// بررسی وضعیت دستورات
$device = Device::find(1);
$pendingCommands = $device->pendingCommands;
$failedCommands = $device->failedCommands;

// Scopes
DeviceCommand::pending()->get();
DeviceCommand::success()->get();
DeviceCommand::failed()->get();

// Attributes
$command->command_data  // آرایه محتوای دستور
$command->response_data // آرایه پاسخ دستگاه
$command->status        // وضعیت (Enum)

// Methods
$command->markAsSent();
$command->markAsSuccess($responseData);
$command->markAsFailed($error, $responseData);
```

### ستون‌های جدول device_commands:

| ستون | نوع | توضیح |
|------|-----|-------|
| device_id | bigint | شناسه دستگاه |
| command_name | string | نام دستور |
| command_content | json | محتوای دستور |
| status | enum | pending, sent, success, failed |
| send_status | boolean | آیا ارسال شده؟ |
| error_count | integer | تعداد خطاها |
| error_message | text | پیام خطا |
| executed_at | datetime | زمان اجرا |
| response | json | پاسخ دستگاه |

## DTOهای موجود

### DTOهای دریافتی (از دستگاه)

#### `AttendanceDTO` - داده‌های استاندارد حضور و غیاب

```php
AttendanceDTO {
    +employeeId: int                        // شناسه کارمند
    +employeeName: string                   // نام کارمند
    +timestamp: Carbon                      // زمان تردد
    +verificationType: VerificationMode     // نوع تأیید هویت (fingerprint, face, card, password)
    +isCheckIn: bool                        // ورود/خروج
    +temperature: ?float                    // دمای بدن (در صورت پشتیبانی دستگاه)
    +deviceSerial: string                   // شماره سریال دستگاه
    +photoBase64: ?string                   // عکس (در صورت وجود)
    +cardNumber: ?int                       // شماره کارت استفاده شده
    +password: ?int                         // پسورد وارد شده
    +eventType: ?AttendanceEventType        // نوع رویداد (ورود، خروج، استراحت، اضافه‌کاری)
    +workCode: ?int                         // کد کار (در صورت استفاده)
    +rawData: array                         // داده‌های خام دستگاه
}
```

**فیلدهای اضافی مفید:**
- `cardNumber`: وقتی کاربر با کارت تردد می‌کند، شماره کارت در اینجا قرار دارد
- `password`: وقتی کاربر با پسورد وارد می‌شود، پسورد در اینجا است
- `eventType`: نوع دقیق رویداد (ورود عادی، استراحت، اضافه‌کاری و...)
- `workCode`: برخی سازمان‌ها از کد کار برای پروژه‌ها استفاده می‌کنند

#### `QrCodeDTO` - داده‌های QR Code / Barcode

```php
QrCodeDTO {
    +qrCodeData: string      // محتوای QR code / Barcode
    +deviceSerial: string    // شماره سریال دستگاه
    +timestamp: Carbon       // زمان اسکن
    +employeeId: ?int        // شناسه کارمند (در صورت مرتبط بودن)
    +rawData: array          // داده‌های خام دستگاه
}
```

**نمونه استفاده:**
```php
// app/Listeners/ProcessQrCodeScan.php
Event::listen(QrCodeReceived::class, function($event) {
    $qrCode = $event->qrCode;
    
    Log::info('QR Code scanned', [
        'device' => $qrCode->deviceSerial,
        'content' => $qrCode->qrCodeData,
    ]);
    
    // پردازش QR code (URL, JSON, plain text, etc.)
});
```

> **نکته:** قابلیت QR Code / Barcode فقط در مدل‌هایی که از این ویژگی پشتیبانی می‌کنند فعال است (مثل برخی مدل‌های AiFace).

#### `UserDTO` - کاربر به همراه داده‌های بایومتریک
- `EnrollmentDTO` - اطلاعات ثبت‌نام کاربر
- `DeviceInfoDTO` - قابلیت‌ها و اطلاعات دستگاه

### DTOهای ارسالی (به دستگاه)

- `AddUserDTO` - افزودن/ویرایش کاربر
- `DeleteUserDTO` - حذف کاربر
- `OpenDoorDTO` - باز کردن درب
- `GetUserListDTO` - دریافت لیست کاربران
- `SetAccessScheduleDTO` - تنظیم برنامه دسترسی
- `SetUserAccessDTO` - تنظیم دسترسی کاربر

## متدهای Facade

```php
// مدیریت کاربران
BiometricDevice::addUser(string $serial, AddUserDTO $dto);
BiometricDevice::deleteUser(string $serial, DeleteUserDTO $dto);
BiometricDevice::getUserList(string $serial);

// کنترل دستگاه
BiometricDevice::openDoor(string $serial, OpenDoorDTO $dto);
BiometricDevice::getDeviceInfo(string $serial);
BiometricDevice::reboot(string $serial);
BiometricDevice::initSystem(string $serial);

// کنترل دسترسی
BiometricDevice::setUserAccess(string $serial, SetUserAccessDTO $dto);
```

## استفاده از چند نوع دستگاه به طور همزمان

پکیج از استفاده همزمان چند نوع دستگاه مختلف پشتیبانی می‌کند.

### تنظیم نوع دستگاه در دیتابیس

هر دستگاه می‌تواند `type` مخصوص به خود را داشته باشد:

```php
use Sajadsoft\BiometricDevices\Models\Device;
use Sajadsoft\BiometricDevices\Enums\DeviceModel;

// ایجاد دستگاه AIFace
$aifaceDevice = Device::create([
    'serial' => 'AIFACE001',
    'name' => 'AIFace Main Entrance',
    'type' => DeviceModel::AI_FACE,
    'ip_address' => '192.168.1.100',
    'port' => 7788,
]);

// ایجاد دستگاه ZKTeco
$zktecoDevice = Device::create([
    'serial' => 'ZKTECO001',
    'name' => 'ZKTeco Back Door',
    'type' => DeviceModel::ZK_TECO,
    'ip_address' => '192.168.1.101',
    'port' => 7788,
]);
```

### استفاده از MapperFactory

برای ارسال دستورات به دستگاه‌های مختلف، از `MapperFactory` استفاده کنید:

```php
use Sajadsoft\BiometricDevices\Services\MapperFactory;

// دریافت mapper مخصوص هر دستگاه
$device = Device::find($deviceId);

// ایجاد mapper بر اساس نوع دستگاه
$mapper = MapperFactory::create(
    $device->type->value,  // 'aiface' or 'zkteco'
    'websocket'            // protocol
);

// استفاده از mapper
$attendanceDTO = $mapper->mapToAttendanceDTO($rawData);
```

### تنظیمات محیط

در فایل `.env` می‌توانید نوع پیش‌فرض دستگاه را تعیین کنید:

```env
BIOMETRIC_DEVICE_TYPE=aiface  # یا zkteco
BIOMETRIC_DRIVER=websocket
```

## پشتیبانی از دستگاه‌های سفارشی

DataMapper خود را برای دستگاه‌های سفارشی ایجاد کنید:

```php
use Sajadsoft\BiometricDevices\Contracts\DataMapperInterface;

class MyDeviceMapper implements DataMapperInterface
{
    public function mapToAttendanceDTO(array $data): AttendanceDTO
    {
        // تبدیل فرمت دستگاه شما به DTO استاندارد
        return new AttendanceDTO(
            employeeId: $data['user_id'],
            employeeName: $data['username'],
            // ...
        );
    }
    
    public function mapAddUserCommand(AddUserDTO $dto): array
    {
        // تبدیل DTO به فرمت دستگاه شما
        return [
            'action' => 'add_employee',
            'emp_id' => $dto->employeeId,
            // ...
        ];
    }
}
```

ثبت در فایل پیکربندی:

```php
'custom_mappers' => [
    'my-device' => \App\Mappers\MyDeviceMapper::class,
],
```

## تست

```bash
composer test
```

## Extend کردن Models

می‌توانید Model های Device و DeviceCommand را برای افزودن ستون‌های سفارشی extend کنید:

```php
// app/Models/Device.php
namespace App\Models;

use Sajadsoft\BiometricDevices\Models\Device as BaseDevice;

class Device extends BaseDevice
{
    public function getFillable()
    {
        return array_merge(parent::getFillable(), [
            'club_id',
            'device_type',
            'status',
        ]);
    }
    
    public function club()
    {
        return $this->belongsTo(Club::class);
    }
}
```

سپس در `config/biometric-devices.php` معرفی کنید:

```php
'models' => [
    'device' => \App\Models\Device::class,
],
```

راهنمای کامل: [EXTENDING-MODELS.md](EXTENDING-MODELS.md)

## 📚 مستندات

### راهنماهای کامل

- **[📖 راهنمای استفاده (Usage Guide)](docs/USAGE.md)** - راهنمای جامع با نمونه‌های عملی
- **[💡 نمونه‌های پیشرفته (Advanced Examples)](docs/EXAMPLES.md)** - نمونه‌های Integration و کاربردهای پیشرفته
- **[🔧 گسترش مدل‌ها (Extending Models)](docs/EXTENDING-MODELS.md)** - راهنمای Extend کردن Models

### Quick Links

| بخش | لینک |
|-----|------|
| نصب و راه‌اندازی | [Installation Guide](docs/USAGE.md#نصب-و-راهاندازی) |
| مدیریت کاربران | [User Management](docs/USAGE.md#مدیریت-کاربران) |
| کنترل دستگاه | [Device Control](docs/USAGE.md#کنترل-دستگاه) |
| رویدادها | [Events Guide](docs/USAGE.md#رویدادها) |
| استفاده پیشرفته | [Advanced Usage](docs/USAGE.md#استفاده-پیشرفته) |
| عیب‌یابی | [Troubleshooting](docs/USAGE.md#عیبیابی-troubleshooting) |

## ساختار پکیج

```
packages/laravel-biometric-devices/
├── config/
│   └── biometric-devices.php       # تنظیمات
├── database/
│   └── migrations/
│       ├── ...create_devices_table.php
│       └── ...create_device_commands_table.php
├── src/
│   ├── Models/
│   │   ├── Device.php              # Base Model (قابل extend)
│   │   └── DeviceCommand.php       # Command tracking
│   ├── Enums/
│   │   ├── DeviceCommandStatusEnum.php
│   │   ├── BiometricType.php
│   │   └── VerificationMode.php
│   ├── Events/                     # رویدادها
│   ├── DTOs/                       # Data Transfer Objects
│   ├── Services/                   # Handlers, Drivers, Mappers
│   └── BiometricDeviceManager.php  # Manager اصلی
└── README.md
```

## نیازمندی‌ها

- PHP 8.2 یا بالاتر
- Laravel 11.0 یا 12.0 یا بالاتر
- افزونه Socket فعال باشد
- جدول `devices` در دیتابیس (خودکار ایجاد می‌شود)

## مجوز

مجوز MIT

## اعتبار

ساخته شده با ❤️ برای جامعه لاراول

## پشتیبانی

برای مشکلات و سوالات:
- [GitHub Issues](https://github.com/sajadsoft1/biometric-device/issues)
- [مستندات](https://github.com/sajadsoft1/biometric-device/wiki)

