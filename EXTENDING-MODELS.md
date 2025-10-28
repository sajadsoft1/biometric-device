# گسترش مدل‌های پکیج (Extending Models)

راهنمای جامع برای extend کردن مدل‌های Device و DeviceCommand

---

## چرا Extend کنیم؟

پکیج `laravel-biometric-devices` شامل مدل‌های پایه است، اما شما ممکن است نیاز داشته باشید:
- ستون‌های اضافی (مثل `club_id`, `status`, `location`)
- روابط بیشتر (مثل `belongsTo(Club)`)
- متدهای سفارشی
- Scope های خاص پروژه

---

## نحوه Extend کردن

### مرحله ۱: Extend کردن Model

#### Device Model

```php
// app/Models/Device.php

namespace App\Models;

use App\Enums\DeviceStatusEnum;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Sajadsoft\BiometricDevices\Models\Device as BaseDevice;

class Device extends BaseDevice
{
    // اضافه کردن ستون‌های جدید به fillable
    protected $fillable = [
        ...parent::$fillable,
        'club_id',
        'device_status',
        'location',
    ];

    // Cast های اضافی
    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'device_status' => DeviceStatusEnum::class,
        ]);
    }

    /** رابطه با Club */
    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    /** Scope برای دستگاه‌های active */
    public function scopeActive($query)
    {
        return $query->where('device_status', DeviceStatusEnum::ACTIVE);
    }

    /** Scope برای دستگاه‌های یک شعبه */
    public function scopeOfClub($query, int $clubId)
    {
        return $query->where('club_id', $clubId);
    }

    /** بررسی block بودن */
    public function isBlocked(): bool
    {
        return $this->device_status === DeviceStatusEnum::BLOCKED;
    }
}
```

#### DeviceCommand Model (اختیاری)

```php
// app/Models/DeviceCommand.php

namespace App\Models;

use Sajadsoft\BiometricDevices\Models\DeviceCommand as BaseDeviceCommand;

class DeviceCommand extends BaseDeviceCommand
{
    // اضافه کردن ویژگی‌های خاص پروژه
    
    public function scopeOfClub($query, int $clubId)
    {
        return $query->whereHas('device', function($q) use ($clubId) {
            $q->where('club_id', $clubId);
        });
    }
}
```

---

### مرحله ۲: ایجاد Migration برای ستون‌های اضافی

```bash
# ابتدا migration پکیج را publish کنید
php artisan vendor:publish --tag=biometric-devices-migrations

# سپس migration جدید برای ستون‌های اضافی بسازید
php artisan make:migration add_custom_fields_to_devices_table
```

```php
// database/migrations/xxxx_add_custom_fields_to_devices_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Enums\DeviceStatusEnum;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->foreignId('club_id')
                ->nullable()
                ->after('id')
                ->constrained()
                ->cascadeOnDelete();
            
            $table->string('device_status')
                ->default(DeviceStatusEnum::ACTIVE->value)
                ->after('is_online');
            
            $table->string('location', 255)
                ->nullable()
                ->after('name');
            
            // اضافه کردن index
            $table->index(['club_id', 'is_online']);
        });
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropForeign(['club_id']);
            $table->dropColumn(['club_id', 'device_status', 'location']);
        });
    }
};
```

---

### مرحله ۳: معرفی Model به Config

```php
// config/biometric-devices.php

return [
    // ... سایر تنظیمات
    
    'models' => [
        // استفاده از Model سفارشی خودتان
        'device'         => \App\Models\Device::class,
        'device_command' => \App\Models\DeviceCommand::class,
    ],
];
```

---

### مرحله ۴: اجرای Migrations

```bash
php artisan migrate
```

---

## مثال کامل: پروژه با Club

### Enum برای وضعیت دستگاه

```php
// app/Enums/DeviceStatusEnum.php

namespace App\Enums;

enum DeviceStatusEnum: string
{
    case ACTIVE  = 'active';
    case BLOCKED = 'blocked';
    case MAINTENANCE = 'maintenance';

    public function label(): string
    {
        return match($this) {
            self::ACTIVE => 'فعال',
            self::BLOCKED => 'مسدود شده',
            self::MAINTENANCE => 'در تعمیر',
        };
    }
}
```

### Model کامل

```php
// app/Models/Device.php

namespace App\Models;

use App\Enums\DeviceStatusEnum;
use App\Enums\DeviceTypeEnum;
use App\Traits\HasPublishedScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Traits\LogsActivity;
use Sajadsoft\BiometricDevices\Models\Device as BaseDevice;

class Device extends BaseDevice
{
    use HasPublishedScope, LogsActivity;

    protected array $extraAttributes = [
        'firmware_version',
        'user_capacity',
        'log_capacity',
    ];

    protected $fillable = [
        ...parent::$fillable,
        'club_id',
        'device_type',
        'device_status',
        'location',
        'published',
    ];

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'device_type'   => DeviceTypeEnum::class,
            'device_status' => DeviceStatusEnum::class,
            'published'     => 'boolean',
        ]);
    }

    /** Relations */
    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    /** Scopes */
    public function scopeActive($query)
    {
        return $query->where('device_status', DeviceStatusEnum::ACTIVE);
    }

    public function scopeBlocked($query)
    {
        return $query->where('device_status', DeviceStatusEnum::BLOCKED);
    }

    public function scopeOfClub($query, int $clubId)
    {
        return $query->where('club_id', $clubId);
    }

    /** Custom Methods */
    public function isBlocked(): bool
    {
        return $this->device_status === DeviceStatusEnum::BLOCKED;
    }

    public function canSendCommands(): bool
    {
        return $this->is_online 
            && $this->device_status === DeviceStatusEnum::ACTIVE;
    }

    public function block(): bool
    {
        return $this->update(['device_status' => DeviceStatusEnum::BLOCKED]);
    }

    public function unblock(): bool
    {
        return $this->update(['device_status' => DeviceStatusEnum::ACTIVE]);
    }
}
```

---

## استفاده از Model های Extended

### در Controller

```php
use App\Models\Device;
use Sajadsoft\BiometricDevices\Facades\BiometricDevice;

class DeviceController extends Controller
{
    public function syncUser(Request $request)
    {
        $device = Device::active()
            ->ofClub(auth()->user()->club_id)
            ->where('serial', $request->device_serial)
            ->firstOrFail();
        
        // بررسی block نبودن
        if ($device->isBlocked()) {
            return response()->error('دستگاه مسدود شده است');
        }
        
        // ارسال دستور
        BiometricDevice::addUser($device->serial, $dto);
        
        return response()->json([
            'message' => 'دستور ارسال شد',
            'device' => $device->name,
            'club' => $device->club->name,
        ]);
    }
}
```

### در Listener

```php
// app/Listeners/UpdateDeviceStatus.php

use App\Models\Device; // مدل extend شده شما

class UpdateDeviceStatus
{
    public function handleConnected(DeviceConnected $event)
    {
        $device = Device::where('serial', $event->deviceSerial)->first();
        
        if (!$device) {
            // ایجاد دستگاه جدید
            $device = Device::create([
                'serial' => $event->deviceSerial,
                'name' => $event->deviceInfo->modelName,
                'club_id' => 1, // یا از کاربر فعلی
                'device_status' => DeviceStatusEnum::ACTIVE,
            ]);
        }
        
        $device->markAsOnline();
        
        // بروزرسانی extra_attributes
        $device->update([
            'extra_attributes' => [
                'firmware_version' => $event->deviceInfo->firmwareVersion,
                'user_capacity' => $event->deviceInfo->userCapacity,
                'log_capacity' => $event->deviceInfo->logCapacity,
            ]
        ]);
    }
}
```

---

## مزایای این رویکرد

### ✅ **برای توسعه‌دهنده پکیج:**
- مدل پایه آماده و کامل
- کاربران نیازی به نوشتن از صفر ندارند
- قابل توسعه و سفارشی‌سازی

### ✅ **برای کاربر پکیج:**
- میتونه مدل رو extend کنه
- میتونه ستون‌های دلخواه اضافه کنه
- میتونه روابط و متدهای سفارشی بنویسه
- کنترل کامل روی دیتابیس

---

## نمونه Migration های کاربر

### اضافه کردن Club

```php
Schema::table('devices', function (Blueprint $table) {
    $table->foreignId('club_id')
        ->after('id')
        ->constrained()
        ->cascadeOnDelete();
});
```

### اضافه کردن Device Status

```php
Schema::table('devices', function (Blueprint $table) {
    $table->string('device_status')
        ->default('active')
        ->after('is_online');
    
    $table->index('device_status');
});
```

### اضافه کردن Device Type

```php
Schema::table('devices', function (Blueprint $table) {
    $table->string('device_type')
        ->default('ai_face')
        ->after('name');
});
```

---

## نکات مهم

### ۱. همیشه از `config('biometric-devices.models.device')` استفاده کنید

```php
// ✅ درست
$deviceModel = config('biometric-devices.models.device');
$device = $deviceModel::where('serial', $serial)->first();

// ❌ اشتباه (hard-coded)
$device = \Sajadsoft\BiometricDevices\Models\Device::where(...);
```

### ۲. Listener ها باید مدل custom را استفاده کنند

```php
// app/Listeners/SaveCommandToDatabase.php

use App\Models\Device; // ✅ مدل extend شده شما

$device = Device::where('serial', $event->deviceSerial)->first();
```

### ۳. Migration های پکیج خودکار اجرا می‌شوند

```bash
# ✅ فقط migrate کنید
php artisan migrate
```

Migration های پکیج با تاریخ `2020_01_01` شروع می‌شوند تا همیشه اول اجرا شوند:
- `2020_01_01_000001_create_devices_table.php`
- `2020_01_01_000002_create_device_commands_table.php`

**چرا 2020؟**
- تضمین اجرای قبل از همه migration های پروژه
- جلوگیری از تداخل در پروژه‌های قدیمی
- ترتیب صحیح foreign key ها

بعد migration های سفارشی خودتان را بسازید:

```bash
php artisan make:migration add_club_id_to_devices_table
```

این migration شما با تاریخ امروز (مثلاً `2025_10_26`) ایجاد می‌شود و **بعد** از migration های پکیج اجرا می‌شود.

---

## مثال واقعی: چند شعبه‌ای (Multi-tenant)

```php
// app/Models/Device.php

class Device extends BaseDevice
{
    protected $fillable = [
        ...parent::$fillable,
        'club_id',
        'device_status',
    ];

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    // Global Scope برای محدود کردن به شعبه کاربر
    protected static function booted()
    {
        static::addGlobalScope('club', function ($query) {
            if (auth()->check() && !auth()->user()->isAdmin()) {
                $query->where('club_id', auth()->user()->club_id);
            }
        });
    }
}
```

حالا:
- کاربران فقط دستگاه‌های شعبه خودشان را می‌بینند
- ادمین همه را می‌بیند
- دستورات فقط به دستگاه‌های مجاز ارسال می‌شود

---

این رویکرد به شما کنترل کامل می‌دهد! 🚀

