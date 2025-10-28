# نمونه‌های استفاده از پکیج دستگاه‌های بایومتریک

## 📘 رویکرد Event-Driven

این پکیج کاملاً رویداد محور است. تمام عملیات از طریق Event و Listener انجام می‌شود.

---

## 🎯 نحوه کار

### جریان کلی:

```
1. شما دستور می‌فرستید (مثلاً getUserList)
2. دستور به دستگاه ارسال می‌شود
3. دستگاه پاسخ می‌دهد
4. Handler پاسخ را پردازش می‌کند
5. Event فایر می‌شود
6. Listener شما اجرا می‌شود
```

---

## 📝 مثال‌های کاربردی

### ۱. دریافت و ذخیره لیست کاربران

```php
// app/Listeners/SyncUsersToDatabase.php

namespace App\Listeners;

use Sajadsoft\BiometricDevices\Events\UserListReceived;
use App\Models\DeviceUser;

class SyncUsersToDatabase
{
    public function handle(UserListReceived $event)
    {
        $deviceSerial = $event->deviceSerial;
        $users = $event->enrollments;
        
        foreach ($users as $enrollment) {
            DeviceUser::updateOrCreate(
                [
                    'device_serial' => $deviceSerial,
                    'employee_id' => $enrollment->employeeId,
                ],
                [
                    'name' => $enrollment->name,
                    'is_admin' => $enrollment->isAdmin,
                    'synced_at' => now(),
                ]
            );
        }
        
        \Log::info("Synced {$event->count} users from device {$deviceSerial}");
    }
}

// app/Providers/AppServiceProvider.php
Event::listen(UserListReceived::class, SyncUsersToDatabase::class);

// استفاده در Controller
BiometricDevice::getUserList('DEVICE_SERIAL');
```

### ۲. ذخیره حضور و غیاب

```php
// app/Listeners/SaveAttendanceToDatabase.php

namespace App\Listeners;

use Sajadsoft\BiometricDevices\Events\AttendanceReceived;
use App\Models\Attendance;

class SaveAttendanceToDatabase
{
    public function handle(AttendanceReceived $event)
    {
        $dto = $event->attendance;
        
        Attendance::create([
            'employee_id' => $dto->employeeId,
            'employee_name' => $dto->employeeName,
            'check_time' => $dto->timestamp,
            'is_check_in' => $dto->isCheckIn,
            'verification_type' => $dto->verificationType->value,
            'temperature' => $dto->temperature,
            'device_serial' => $dto->deviceSerial,
            // فیلدهای جدید ⬇️
            'card_number' => $dto->cardNumber,      // شماره کارت استفاده شده
            'password' => $dto->password,            // پسورد وارد شده
            'event_type' => $dto->eventType?->value, // نوع رویداد (ورود/خروج/استراحت/...)
            'work_code' => $dto->workCode,           // کد کار/پروژه
        ]);
        
        \Log::info("Attendance recorded for employee {$dto->employeeId}", [
            'verification' => $dto->verificationType->value,
            'card' => $dto->cardNumber,
            'event' => $dto->eventType?->description(),
        ]);
    }
}

// ثبت در EventServiceProvider
Event::listen(AttendanceReceived::class, SaveAttendanceToDatabase::class);
```

### ۳. پردازش QR Code / Barcode

برخی مدل‌های دستگاه (مثل AiFace) قابلیت اسکن QR code و Barcode دارند:

```php
// app/Listeners/ProcessQrCodeScan.php

namespace App\Listeners;

use Sajadsoft\BiometricDevices\Events\QrCodeReceived;
use App\Models\VisitorLog;

class ProcessQrCodeScan
{
    public function handle(QrCodeReceived $event)
    {
        $qrCode = $event->qrCode;
        
        // مثال 1: QR code شامل URL
        if (filter_var($qrCode->qrCodeData, FILTER_VALIDATE_URL)) {
            // پردازش URL
            $this->processVisitorUrl($qrCode->qrCodeData, $qrCode->deviceSerial);
            return;
        }
        
        // مثال 2: QR code شامل JSON
        try {
            $data = json_decode($qrCode->qrCodeData, true);
            if (isset($data['visitor_id'])) {
                // ثبت ورود مهمان
                VisitorLog::create([
                    'visitor_id' => $data['visitor_id'],
                    'device_serial' => $qrCode->deviceSerial,
                    'scanned_at' => $qrCode->timestamp,
                ]);
            }
        } catch (\Exception $e) {
            \Log::warning('Invalid QR code format', [
                'content' => $qrCode->qrCodeData,
                'device' => $qrCode->deviceSerial,
            ]);
        }
        
        // مثال 3: Barcode محصول (برای انبارداری)
        if (preg_match('/^[0-9]{13}$/', $qrCode->qrCodeData)) {
            // پردازش EAN-13 barcode
            $this->processProductBarcode($qrCode->qrCodeData);
        }
    }
}

// ثبت در EventServiceProvider
Event::listen(QrCodeReceived::class, ProcessQrCodeScan::class);
```

**کاربردهای QR Code:**
- ✅ مدیریت بازدیدکنندگان (Visitor Management)
- ✅ صدور بلیط و ورود به رویدادها
- ✅ مدیریت انبار و پیگیری محصولات
- ✅ احراز هویت دو مرحله‌ای
- ✅ لینک به سیستم‌های خارجی

### ۴. پردازش نتیجه افزودن کاربر

```php
// app/Listeners/HandleUserAddResponse.php

namespace App\Listeners;

use Sajadsoft\BiometricDevices\Events\CommandResponseReceived;
use App\Models\DeviceSync;

class HandleUserAddResponse
{
    public function handle(CommandResponseReceived $event)
    {
        // فقط برای دستور setuserinfo
        if ($event->commandName !== 'setuserinfo') {
            return;
        }
        
        if ($event->success) {
            \Log::info("User added successfully to device {$event->deviceSerial}");
            
            // بروزرسانی وضعیت sync
            DeviceSync::where('device_serial', $event->deviceSerial)
                ->where('command', 'add_user')
                ->where('status', 'pending')
                ->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                ]);
        } else {
            \Log::error("Failed to add user to device {$event->deviceSerial}");
            
            DeviceSync::where('device_serial', $event->deviceSerial)
                ->where('command', 'add_user')
                ->where('status', 'pending')
                ->update([
                    'status' => 'failed',
                    'error' => json_encode($event->responseData),
                    'failed_at' => now(),
                ]);
        }
    }
}

// ثبت
Event::listen(CommandResponseReceived::class, HandleUserAddResponse::class);

// استفاده
$dto = new AddUserDTO(
    employeeId: 1001,
    name: 'علی احمدی',
    biometricType: BiometricType::FACE,
    biometricData: $faceTemplate
);

BiometricDevice::addUser('DEVICE_SERIAL', $dto);
```

### ۴. نمایش پیام به کاربر بعد از باز شدن درب

```php
// app/Listeners/NotifyDoorOpened.php

namespace App\Listeners;

use Sajadsoft\BiometricDevices\Events\CommandResponseReceived;

class NotifyDoorOpened
{
    public function handle(CommandResponseReceived $event)
    {
        if ($event->commandName !== 'opendoor') {
            return;
        }
        
        if ($event->success) {
            // ارسال نوتیفیکیشن به ادمین
            \Notification::send(
                User::role('admin')->get(),
                new DoorOpenedNotification($event->deviceSerial)
            );
            
            // ثبت لاگ امنیتی
            SecurityLog::create([
                'event' => 'door_opened',
                'device' => $event->deviceSerial,
                'timestamp' => now(),
            ]);
        }
    }
}

// استفاده
BiometricDevice::openDoor('DEVICE_SERIAL', new OpenDoorDTO(doorNumber: 1));
```

### ۵. مدیریت اتصال و قطع دستگاه

```php
// app/Listeners/UpdateDeviceStatus.php

namespace App\Listeners;

use Sajadsoft\BiometricDevices\Events\DeviceConnected;
use Sajadsoft\BiometricDevices\Events\DeviceDisconnected;
use App\Models\Device;

class UpdateDeviceStatus
{
    public function handleConnected(DeviceConnected $event)
    {
        Device::where('serial', $event->deviceInfo->serialNumber)
            ->update([
                'status' => 'online',
                'last_connected' => now(),
                'model' => $event->deviceInfo->modelName,
                'firmware' => $event->deviceInfo->firmwareVersion,
            ]);
        
        \Log::info("Device {$event->deviceInfo->serialNumber} connected");
    }
    
    public function handleDisconnected(DeviceDisconnected $event)
    {
        Device::where('serial', $event->deviceSerial)
            ->update([
                'status' => 'offline',
                'last_disconnected' => now(),
            ]);
        
        \Log::warning("Device {$event->deviceSerial} disconnected");
    }
}

// ثبت
Event::listen(DeviceConnected::class, [UpdateDeviceStatus::class, 'handleConnected']);
Event::listen(DeviceDisconnected::class, [UpdateDeviceStatus::class, 'handleDisconnected']);
```

---

## 🎨 مثال کامل: Controller برای افزودن کاربر

```php
// app/Http/Controllers/EmployeeController.php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Http\Requests\StoreEmployeeRequest;
use Sajadsoft\BiometricDevices\Facades\BiometricDevice;
use Sajadsoft\BiometricDevices\DTOs\Commands\AddUserDTO;
use Sajadsoft\BiometricDevices\Enums\BiometricType;

class EmployeeController extends Controller
{
    public function store(StoreEmployeeRequest $request)
    {
        // 1. ذخیره کارمند در دیتابیس
        $employee = Employee::create([
            'employee_id' => $request->employee_id,
            'name' => $request->name,
            'face_template' => $request->face_template,
        ]);
        
        // 2. ارسال به دستگاه
        $dto = new AddUserDTO(
            employeeId: $employee->employee_id,
            name: $employee->name,
            biometricType: BiometricType::FACE,
            biometricData: $employee->face_template,
            isAdmin: $request->boolean('is_admin')
        );
        
        BiometricDevice::addUser($request->device_serial, $dto);
        
        // 3. ثبت درخواست sync در جدول کمکی
        $employee->deviceSyncs()->create([
            'device_serial' => $request->device_serial,
            'command' => 'add_user',
            'status' => 'pending',
        ]);
        
        // 4. بازگشت به کاربر
        return response()->json([
            'message' => 'کارمند ذخیره شد. درخواست افزودن به دستگاه ارسال شد.',
            'employee' => $employee,
        ]);
    }
}
```

---

## 🔔 Laravel Auto-Discovery (Laravel 11+)

### ✨ کشف خودکار Listener ها

در **Laravel 11 و بالاتر**، دیگر نیازی نیست که Listener ها را در `AppServiceProvider` یا `EventServiceProvider` ثبت کنید!

#### چطور کار می‌کند؟

Laravel به صورت خودکار فایل‌های `app/Listeners` را اسکن می‌کند و متدهایی که type-hint دارند را به Event های مربوطه متصل می‌کند:

```php
// app/Listeners/SaveAttendance.php
// ✅ به صورت خودکار ثبت می‌شود - نیازی به ثبت دستی نیست!

namespace App\Listeners;

use Sajadsoft\BiometricDevices\Events\AttendanceReceived;

class SaveAttendance
{
    // Laravel می‌بیند که این متد AttendanceReceived می‌خواهد
    // و به طور خودکار وصل می‌کند
    public function handle(AttendanceReceived $event)
    {
        // ذخیره در دیتابیس
    }
}
```

#### مثال با چند متد:

```php
// app/Listeners/UpdateDeviceStatus.php
// ✅ هر دو متد خودکار ثبت می‌شوند

namespace App\Listeners;

use Sajadsoft\BiometricDevices\Events\DeviceConnected;
use Sajadsoft\BiometricDevices\Events\DeviceDisconnected;

class UpdateDeviceStatus
{
    // خودکار متصل به DeviceConnected
    public function handleConnected(DeviceConnected $event)
    {
        // ...
    }
    
    // خودکار متصل به DeviceDisconnected
    public function handleDisconnected(DeviceDisconnected $event)
    {
        // ...
    }
}
```

#### بررسی Listener های ثبت شده:

```bash
php artisan event:list
```

خروجی:
```
Sajadsoft\BiometricDevices\Events\DeviceConnected
  ⇂ App\Listeners\UpdateDeviceStatus@handleConnected

Sajadsoft\BiometricDevices\Events\AttendanceReceived
  ⇂ App\Listeners\SaveAttendance@handle
```

#### غیرفعال کردن Auto-Discovery:

اگر می‌خواهید کنترل کامل داشته باشید:

```php
// app/Providers/AppServiceProvider.php

public function boot(): void
{
    // غیرفعال کردن Auto-Discovery
    Event::shouldDiscoverEvents(false);
    
    // سپس فقط Listener های مورد نظر را ثبت کنید
    Event::listen(
        AttendanceReceived::class,
        SaveAttendanceToDatabase::class,
    );
    
    // این Listener ثبت نمی‌شود
    // Event::listen(DeviceConnected::class, ...);
}
```

#### نکات مهم:

- ✅ **Laravel 11+**: Auto-Discovery فعال است (پیش‌فرض)
- ✅ **Laravel 10 و قبل‌تر**: باید در `EventServiceProvider` ثبت کنید
- ⚠️ **حذف Listener**: برای غیرفعال کردن یک Listener:
  - فایل را rename کنید (مثلاً `.bak` اضافه کنید)
  - یا فایل را حذف کنید
  - یا Auto-Discovery را غیرفعال کنید
- ✅ **کامنت کردن کافی نیست**: اگر در `AppServiceProvider` کامنت کنید، Laravel همچنان Auto-Discovery می‌کند!

---

## 💡 نکات مهم

### ✅ مزایای Event-Driven:

1. **مستقل از دیتابیس** - شما تصمیم می‌گیرید چه چیزی را کجا ذخیره کنید
2. **غیر مسدود کننده** - درخواست‌ها سریع برمی‌گردند
3. **قابل ردیابی** - می‌توانید از جدول کمکی برای tracking استفاده کنید
4. **انعطاف‌پذیر** - می‌توانید چندین Listener به یک Event وصل کنید

### ⚠️ چالش‌ها:

1. **پاسخ فوری ندارد** - باید از روش‌های جایگزین استفاده کنید:
   - ذخیره در جدول و polling
   - WebSocket برای real-time update
   - صف (Queue) برای پردازش بعدی

2. **نیاز به Listener** - باید برای هر Event مربوطه Listener تعریف کنید

---

## 📋 خلاصه Event ها

| Event | کاربرد | Listener نمونه |
|-------|---------|----------------|
| `AttendanceReceived` | حضور و غیاب خودکار | ذخیره در جدول attendance |
| `DeviceConnected` | اتصال دستگاه | بروزرسانی وضعیت آنلاین |
| `DeviceDisconnected` | قطع دستگاه | بروزرسانی وضعیت آفلاین |
| `UserListReceived` | لیست کاربران | همگام‌سازی کاربران |
| `CommandResponseReceived` | پاسخ دستورات | بروزرسانی وضعیت دستور |

---

همه چیز Event-driven است! 🎉
