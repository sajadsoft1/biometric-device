# تغییرات

## 13.1.0 — 2026-11-05

- دستورهای جاافتاده پروتکل AI Face اضافه شد: `getquestionnaire`، `setquestionnaire`، `getholiday`، `setholiday`، `getuserprofile`، `setuserprofile`، `getusername`، `enableuser`، `cleanuser`، `setdevinfo`، `gettime`، `getdevlock`، `getuserlock`، `deleteuserlock` و `cleanuserlock`.
- پرسشنامه انتخاب تکی را تا ۱۶ گزینه و انتخاب چندتایی را تا ۸ گزینه می‌پذیرد. برنامه زمانی حداکثر ۸ بازه است و اگر `voice` داده نشود در پیام ارسال نمی‌شود.
- تعطیلات حداکثر ۳۰ مورد است و تاریخ شروع و پایان به شکل `MM-DD` است. پروفایل کاربر حداکثر ۲۰۰ بایت است و `enrollid` صفر یعنی اطلاعات عمومی.
- `setdevinfo` فقط فیلدهایی را می‌فرستد که مقدار دارند.
- تنظیم پارامتر دسترسی دستگاه (`dayzone`، `weekzone`، `lockgroup` و بقیه فیلدهای پروتکل) با `setAccessParameter` انجام می‌شود. متد قبلی `setDeviceLock` همچنان فقط پرچم `locked` را می‌فرستد.
- `setuserlock` در صورت مقدار داشتن، `weekzone2` تا `weekzone4` را هم ارسال می‌کند.
- `getalllog` بازه `from` و `to` را به شکل `Y-m-d` می‌پذیرد.
- پاسخ این دستورها وضعیت فرمان ذخیره‌شده را به‌روز می‌کند و رویداد `CommandResponseReceived` را منتشر می‌کند. اگر `getquestionnaire` یا `getholiday` بسته بعدی داشته باشند، ادامه با `stn: false` درخواست می‌شود.

## 13.0.1 — 2026-09-15

- جایگزینی Handler با تنظیمات اکنون به‌صورت صریح برای همهٔ فرمان‌های عملیاتی مستند و آزموده شد.
- معتبر بودن کلاس سفارشی با `CommandHandlerInterface` بررسی می‌شود.

## 13.0.0 — 2026-09-15

- نسخهٔ پکیج در `composer.json` با انتشار `13.0.0` هماهنگ شد.
- امکان جایگزینی رسمی Handler هر فرمان با `biometric-devices.handlers` اضافه شد؛ از جمله `sendqrcode`.
- پاسخ پیش‌فرض QR اصلاح شد تا شناسهٔ کاربر را مستقیماً از پیام دستگاه بخواند.
- آزمون‌های QR برای انتشار رویداد، پیام ناقص و Handler سفارشی افزوده شد.
- راهنمای مرجع فارسی برای نصب، رویدادها، دستورها، QR، سفارشی‌سازی و عیب‌یابی افزوده شد.
