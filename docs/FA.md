# راهنمای فارسی پکیج Biometric Device

این پکیج یک سرور WebSocket در لاراول است که پیام دستگاه را می‌گیرد، به دادهٔ استاندارد تبدیل می‌کند و به برنامهٔ شما رویداد می‌دهد. نسخهٔ فعلی برای دستگاه‌های سازگار با پروتکل AIFace WebSocket آماده است.

## پیش‌نیازها و سازگاری

- PHP 8.2 یا بالاتر
- Laravel 11، 12 یا 13
- افزونهٔ `sockets` در PHP برای اجرای سرور WebSocket
- SQLite/MySQL/PostgreSQL برای جدول‌های دستگاه و دستورها

## نصب و راه‌اندازی

```bash
composer require sajadsoft1/biometric-device
php artisan vendor:publish --tag=biometric-devices-config
php artisan migrate
php artisan biometric:start-server
```

فایل `.env` را متناسب با شبکهٔ خود تنظیم کنید:

```env
BIOMETRIC_WS_HOST=0.0.0.0
BIOMETRIC_WS_PORT=7788
BIOMETRIC_DRIVER=websocket
BIOMETRIC_DEVICE_TYPE=aiface
BIOMETRIC_LOG_ENABLED=true
```

در تنظیمات دستگاه، IP سرور لاراول و پورت `7788` را وارد کنید. اگر سرور پشت فایروال است، همین پورت باید فقط برای شبکهٔ مجاز باز باشد.

برای محیط عملیاتی (production)، فرمان سرور را با Supervisor یا systemd همیشه‌فعال نگه دارید؛ اجرای آن با مرورگر یا درخواست وب مناسب نیست.

## مدل ذهنی جریان داده

```
دستگاه → WebSocket → Pipeline → Handler → DTO → Event → برنامهٔ شما
برنامهٔ شما → Facade → دستور ذخیره‌شده → WebSocket → دستگاه
```

- **Pipeline** (زنجیرهٔ پردازش) پیام را اعتبارسنجی، نرمال‌سازی و به Handler می‌رساند.
- **Handler** (کلاس پردازش پیام) پاسخ دستگاه یا رویداد را می‌سازد.
- **DTO** (شیء انتقال داده) قالبی مستقل از برند دستگاه برای داده‌هایی مثل تردد، کاربر و QR است.
- **Event** (رویداد) نقطهٔ اتصال امن برنامهٔ شما به پکیج است.

## رویدادهای اصلی

| رویداد | زمان اجرا | دادهٔ اصلی |
| --- | --- | --- |
| `AttendanceReceived` | ثبت تردد | `AttendanceDTO` |
| `QrCodeReceived` | اسکن QR یا بارکد | `QrCodeDTO` |
| `DeviceConnected` | ثبت یا اتصال دستگاه | مدل `Device` و اطلاعات دستگاه |
| `DeviceDisconnected` | قطع اتصال | مدل `Device` |
| `UserInfoReceived` / `UserListReceived` | دریافت کاربر یا فهرست کاربران | `UserDTO` |
| `FingerprintEnrollmentStarted` / `RegistrationStatusReceived` | فرایند ثبت اثر انگشت | DTO ثبت‌نام |
| `CommandSent` / `CommandResponseReceived` | ارسال یا پاسخ دستور | مدل `DeviceCommand` |

نمونهٔ ثبت تردد در برنامهٔ هدف:

```php
namespace App\Listeners;

use App\Models\AttendanceLog;
use Sajadsoft\BiometricDevices\Events\AttendanceReceived;

class StoreAttendance
{
    public function handle(AttendanceReceived $event): void
    {
        $attendance = $event->attendance;

        AttendanceLog::create([
            'employee_id' => $attendance->employeeId,
            'occurred_at' => $attendance->timestamp,
            'device_serial' => $attendance->deviceSerial,
        ]);
    }
}
```

در Laravel 11 به بعد، Listenerهای دارای type-hint معمولاً خودکار کشف می‌شوند. اگر این قابلیت در برنامه غیرفعال است، Listener را در محل ثبت رویدادهای برنامهٔ خود ثبت کنید.

## ارسال دستور به دستگاه

Facade (نمایندهٔ سادهٔ سرویس) `BiometricDevice` دستور را در `device_commands` ذخیره می‌کند. سرور آن را ارسال می‌کند و پاسخ دستگاه وضعیت را به `success` یا `failed` تغییر می‌دهد.

```php
use Sajadsoft\BiometricDevices\DTOs\Commands\OpenDoorDTO;
use Sajadsoft\BiometricDevices\Facades\BiometricDevice;

BiometricDevice::getUserList('DEVICE-SERIAL');
BiometricDevice::getDeviceInfo('DEVICE-SERIAL');
BiometricDevice::openDoor('DEVICE-SERIAL', new OpenDoorDTO(doorNumber: 1));
```

برای پایش دستورها، از مدل `DeviceCommand` استفاده کنید. وضعیت‌های رایج `pending`، `sent`، `success` و `failed` هستند. دادهٔ بایومتریک را در لاگ عمومی ذخیره نکنید.

## QR: استفادهٔ معمول و پاسخ پیش‌فرض

وقتی دستگاه `sendqrcode` می‌فرستد، پکیج یک `QrCodeReceived` منتشر می‌کند. مقدار QR از `record` خوانده می‌شود و برای سازگاری، `qrcode` و `qr` نیز پذیرفته می‌شوند. پاسخ پیش‌فرض دسترسی را رد می‌کند (`access: 0`) تا برنامه بدون منطق احراز هویت، ناخواسته اجازهٔ ورود ندهد.

برای ثبت رخداد QR، یک Listener کافی است:

```php
use Sajadsoft\BiometricDevices\Events\QrCodeReceived;

class AuditQrScan
{
    public function handle(QrCodeReceived $event): void
    {
        QrScan::create([
            'value' => $event->qrCode->qrCodeData,
            'device_serial' => $event->qrCode->deviceSerial,
            'scanned_at' => $event->qrCode->timestamp,
        ]);
    }
}
```

## بازنویسی پردازش QR در برنامهٔ هدف

اگر پاسخ به دستگاه به وضعیت کاربر وابسته است، Handler پیش‌فرض را جایگزین کنید. کلاس سفارشی باید `SendQrCodeHandler` را گسترش دهد و `handle` را بازنویسی کند. پاسخ نمونهٔ زیر فقط پس از یافتن عضو فعال اجازه می‌دهد:

```php
namespace App\Biometric\Handlers;

use App\Models\Member;
use Sajadsoft\BiometricDevices\Services\CommandHandlers\SendQrCodeHandler;

class AuthorizeQrCodeHandler extends SendQrCodeHandler
{
    public function handle(array $data, $connection): ?array
    {
        $value = $data['record'] ?? $data['qrcode'] ?? $data['qr'] ?? null;
        $member = $value ? Member::query()->where('qr_token', $value)->first() : null;
        $allowed = $member?->is_active === true;

        return [
            'ret' => 'sendqrcode',
            'result' => true,
            'access' => $allowed ? 1 : 0,
            'enrollid' => $member?->id,
            'message' => $allowed ? 'Access granted' : 'Access denied',
            'voice' => $allowed ? 'Access granted' : 'Access denied',
        ];
    }
}
```

سپس در فایل منتشرشدهٔ `config/biometric-devices.php` ثبتش کنید:

```php
'handlers' => [
    'sendqrcode' => App\Biometric\Handlers\AuthorizeQrCodeHandler::class,
],
```

پس از تغییر تنظیمات کش‌شده، `php artisan config:clear` را اجرا کنید. این روش فقط منطق QR برنامهٔ شما را تغییر می‌دهد و به کد پکیج دست نمی‌زند؛ بنابراین به‌روزرسانی پکیج ساده و کم‌خطر می‌ماند.

## جایگزینی همهٔ Handlerهای عملیاتی

کلیدهای `biometric-devices.handlers` برای همهٔ فرمان‌های شناخته‌شده بررسی می‌شوند، نه فقط QR. بنابراین می‌توانید کلاس موردنظر برنامهٔ خود را برای هر فرمان عملیاتی تعیین کنید. کلاس جایگزین باید `CommandHandlerInterface` (قرارداد دو متد `handle` و `getCommandName`) را پیاده‌سازی کند؛ در غیر این صورت پکیج با خطای شفاف متوقف می‌شود.

| کلید تنظیمات | کاربرد |
| --- | --- |
| `reg` | ثبت دستگاه |
| `sendlog` | دریافت تردد |
| `sendqrcode` | اسکن QR یا بارکد |
| `senduser` | دریافت کاربر از دستگاه |
| `getuserlist` / `getuserinfo` | پاسخ فهرست یا اطلاعات کاربر |
| `checkregstatus` | وضعیت ثبت اثر انگشت یا دادهٔ بایومتریک |
| سایر نام‌های موجود در `DeviceCommand` | هر فرمان پشتیبانی‌شدهٔ دیگر |

نمونهٔ جایگزینی Handler تردد:

```php
'handlers' => [
    'sendlog' => App\Biometric\Handlers\StoreAttendanceHandler::class,
    'sendqrcode' => App\Biometric\Handlers\AuthorizeQrCodeHandler::class,
],
```

برای حفظ رفتار پیش‌فرض، کلید آن فرمان را از آرایه حذف کنید. پس از هر تغییر در تنظیمات کش‌شده، `php artisan config:clear` را اجرا کنید.

## افزودن پشتیبانی از برند یا پروتکل جدید

برای تفاوت در قالب پیام‌های دستگاه، یک Mapper بسازید که `DataMapperInterface` را پیاده‌سازی کند یا `AIFaceWebSocketMapper` را گسترش دهد. سپس آن را در بخش `custom_mappers` تنظیمات معرفی کنید. Mapper مسئول تبدیل قالب اختصاصی دستگاه به DTOهای پکیج و برعکس است؛ منطق کسب‌وکار مثل ثبت تردد یا مجوز ورود باید در Listener یا Handler برنامه بماند.

## مدل‌ها و سفارشی‌سازی دیتابیس

پکیج مدل‌های `Device` و `DeviceCommand` را دارد. برای افزودن ستون‌هایی مانند `branch_id` یا `club_id`:

1. migration خودتان را بسازید.
2. مدل پکیج را در مدل برنامه گسترش دهید.
3. نام کلاس جدید را در `biometric-devices.models.device` یا `device_command` تنظیم کنید.

راهنمای گام‌به‌گام در [EXTENDING-MODELS.md](../EXTENDING-MODELS.md) است.

## عیب‌یابی سریع

| نشانه | بررسی |
| --- | --- |
| دستگاه وصل نمی‌شود | IP/پورت سرور، فایروال و اجرای فرمان `biometric:start-server` را بررسی کنید. |
| دستور ارسال نمی‌شود | وجود دستگاه با همان serial، اتصال آنلاین و وضعیت `pending` در `device_commands` را بررسی کنید. |
| Handler سفارشی اجرا نمی‌شود | کلید دقیق فرمان (`sendqrcode`) و پاک‌سازی config cache را بررسی کنید. |
| پاسخ QR اشتباه است | نام فیلد واقعی دستگاه را ثبت کنید؛ پکیج به‌ترتیب `record`، `qrcode` و `qr` را می‌خواند. |

## آزمون و مشارکت

پیش از ارسال تغییرات، وابستگی‌ها را نصب و آزمون‌ها را اجرا کنید:

```bash
composer install
vendor/bin/phpunit
```

برای تغییر در پروتکل، دست‌کم یک آزمون برای پیام معتبر، پیام ناقص و پاسخ مورد انتظار دستگاه اضافه کنید. داده‌های چهره، اثر انگشت و QR واقعی را در fixture (دادهٔ آزمون) یا گزارش خطا وارد نکنید.
