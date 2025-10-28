# راهنمای نصب و راه‌اندازی

راهنمای گام‌به‌گام نصب پکیج laravel-biometric-devices

---

## گام ۱: نصب پکیج

```bash
composer require your-company/laravel-biometric-devices
```

## گام ۲: انتشار فایل پیکربندی

```bash
php artisan vendor:publish --tag=biometric-devices-config
```

این دستور فایل `config/biometric-devices.php` را ایجاد می‌کند.

## گام ۳: اجرای Migrations

Migration های پکیج به صورت خودکار load می‌شوند:

```bash
php artisan migrate
```

این دستور دو جدول ایجاد می‌کند:
- `devices` - دستگاه‌های بایومتریک
- `device_commands` - دستورات ارسالی به دستگاه‌ها

**نکته مهم:** Migration های پکیج با prefix `2020_01_01` شروع می‌شوند تا همیشه **قبل** از migration های پروژه شما اجرا شوند و تداخلی ایجاد نکنند.

**اگر جدول devices از قبل وجود دارد:** Migration به صورت هوشمند این را تشخیص داده و skip می‌کند.

## گام ۴: Extend کردن Device Model (اختیاری)

اگر نیاز به ستون‌های اضافی دارید:

### ۴.۱ ایجاد Migration سفارشی

```bash
php artisan make:migration add_custom_fields_to_devices_table
```

```php
// database/migrations/xxxx_add_custom_fields_to_devices_table.php

public function up(): void
{
    Schema::table('devices', function (Blueprint $table) {
        $table->foreignId('club_id')
            ->nullable()
            ->after('id')
            ->constrained()
            ->cascadeOnDelete();
        
        $table->string('device_type')
            ->default('ai_face')
            ->after('name');
        
        $table->string('status')
            ->default('active')
            ->after('is_online');
    });
}
```

### ۴.۲ Extend کردن Model

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

### ۴.۳ معرفی به Config

```php
// config/biometric-devices.php

'models' => [
    'device' => \App\Models\Device::class, // مدل سفارشی شما
],
```

## گام ۵: تنظیمات محیطی (.env)

```env
# WebSocket Configuration
BIOMETRIC_WS_HOST=0.0.0.0
BIOMETRIC_WS_PORT=7788
BIOMETRIC_DRIVER=websocket

# Logging
BIOMETRIC_LOG_ENABLED=true
BIOMETRIC_LOG_CHANNEL=daily
```

## گام ۶: ثبت Event Listeners

```php
// app/Providers/AppServiceProvider.php

use Sajadsoft\BiometricDevices\Events\AttendanceReceived;
use Sajadsoft\BiometricDevices\Events\CommandResponseReceived;
use Sajadsoft\BiometricDevices\Events\DeviceConnected;

public function boot(): void
{
    // حضور و غیاب
    Event::listen(
        AttendanceReceived::class,
        \App\Listeners\SaveAttendanceToDatabase::class
    );
    
    // اتصال دستگاه
    Event::listen(
        DeviceConnected::class,
        [\App\Listeners\UpdateDeviceStatus::class, 'handleConnected']
    );
    
    // پاسخ دستورات
    Event::listen(
        CommandResponseReceived::class,
        \App\Listeners\UpdateCommandStatus::class
    );
}
```

## گام ۷: ایجاد Listeners

```bash
php artisan make:listener SaveAttendanceToDatabase
php artisan make:listener UpdateDeviceStatus
php artisan make:listener SaveCommandToDatabase
php artisan make:listener UpdateCommandStatus
```

مثال Listener:

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
            'check_time' => $dto->timestamp,
            'is_check_in' => $dto->isCheckIn,
        ]);
    }
}
```

## گام ۸: راه‌اندازی سرور

```bash
# در ترمینال یا با Supervisor
php artisan biometric:start-server
```

### تنظیم Supervisor (توصیه می‌شود)

```ini
[program:biometric-server]
process_name=%(program_name)s
command=php /path/to/project/artisan biometric:start-server
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/log/biometric-server.log
```

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start biometric-server
```

## گام ۹: تست اتصال

```bash
php artisan tinker
```

```php
use Sajadsoft\BiometricDevices\Facades\BiometricDevice;

// دریافت اطلاعات دستگاه
BiometricDevice::getDeviceInfo('DEVICE_SERIAL');

// بررسی دستورات
\Sajadsoft\BiometricDevices\Models\DeviceCommand::latest()->get();

// بررسی دستگاه‌های آنلاین
\App\Models\Device::where('is_online', true)->get();
```

---

## عیب‌یابی

### سرور شروع نمی‌شود

```bash
# بررسی پورت
netstat -an | grep 7788

# kill کردن process قبلی
sudo lsof -t -i:7788 | xargs kill -9
```

### دستگاه متصل نمی‌شود

1. IP و Port دستگاه را چک کنید
2. Firewall را بررسی کنید
3. لاگ‌ها را مشاهده کنید: `storage/logs/laravel.log`

### دستورات ارسال نمی‌شوند

1. سرور WebSocket باید در حال اجرا باشد
2. دستگاه باید آنلاین باشد (`is_online = true`)
3. دستورات در جدول `device_commands` چک شوند

---

## آماده است! 🚀

حالا می‌توانید:
- حضور و غیاب دریافت کنید
- کاربران را مدیریت کنید
- دستورات را ارسال کنید
- دستگاه‌ها را کنترل کنید

مستندات بیشتر:
- [README.md](README.md) - مستندات اصلی
- [EXTENDING-MODELS.md](EXTENDING-MODELS.md) - راهنمای extend
- [USAGE-EXAMPLES.md](USAGE-EXAMPLES.md) - مثال‌های کاربردی

