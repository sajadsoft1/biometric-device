# protocol-intro.md

# معرفی — WebSocket + JSON Protocol v2.6

این سند مرجع فنی پروتکل **WebSocket + JSON (v2.6)** مربوط به دستگاه‌های حضور و غیاب/کنترل دسترسی برند Cloud Solution است. هدف: ارائه مستندات کامل برای پیاده‌سازی سرور که با دستگاه‌ها از طریق WebSocket (RFC6455) و پیام‌های JSON تعامل کند.

* **پورت پیش‌فرض:** `7788` (بدون TLS)
* **فرمت پیام:** JSON (کلیدها با حروف کوچک)
* **انکدینگ:** UTF-8
* **نکات کلی:**

    * همه پیام‌ها به‌صورت یک شئ JSON ارسال می‌شوند.
    * همه فیلدها نام‌های انگلیسی کوچک دارند. مقادیر متنی UTF‑8 هستند.
    * `backupnum` نشان‌دهنده نوع داده کاربر (فینگرپرینت، کارت، رمز، عکس و غیره) است.

---

## ساختار کلی پیام

هر پیام شامل حداقل یک فیلد کنترلی `cmd` است که نوع دستور را مشخص می‌کند. بسته به طرف فرستنده ممکن است فیلدهای اضافه (`sn`, `enrollid`, `record`, `count`, ...) وجود داشته باشد.

الگوی عمومی:

```json
{
  "cmd": "<command>",
  "sn": "<terminal_sn>",
  "...": "..."
}
```

پاسخ از طرف مقابل اغلب دارای `ret`, `result` و در صورت خطا `reason` است:

```json
{
  "ret": "<command>",
  "result": true,
  "cloudtime": "2016-03-25 13:49:30"
}
```

---

## جدول استاندارد برای پارامترها

تمام جداول پارامترها در مستندهای جداگانه با ستون‌های زیر ارائه می‌شوند:

| Key | Type | Description | Example | Default | Range/Limit |
|-----|------|-------------|---------|---------|-------------|

---

## نگاشت مهم‌ها (Enums)

### `backupnum`

* `0`–`9` : fingerprint slots (هرکدام یک اثر انگشت)
* `10` : password
* `11` : RFID card
* `20`–`27` : static face
* `30`–`37` : parlm (?) (در مستند اصلی مقدار ذکر شده)
* `50` : photo (Base64)

### `verifymode` (نمادهای رایج)

* `0` : fingerprint/card/password (پیش‌فرض)
* `1` : card + fingerprint
* `2` : password + fingerprint
* `3` : card + fingerprint + password
* `4` : card + password
* `13` : QR-code (در بعضی دستگاه‌ها نشان داده)

### `language` (نمونه)

* `0` == `EN`
* `1` == Simplified Chinese
* `2` == Traditional Chinese
* ...
* `18` == `Farsi`

---

## نکات پیاده‌سازی (برای تیم سرور)

* هر کانکشن WebSocket می‌تواند نماینده یک دستگاه (`sn`) باشد؛ on connect باید `register` را انتظار داشته باشید یا از cache استفاده کنید.
* برای هر `cmd` یک handler جدا بسازید (pattern: `handle_<cmd>(payload, ws)`).
* هر پاسخ به‌صورت JSON و با `ret` برابر با `cmd` یا با مقدار پاسخ مناسب ارسال شود.
* اگر پیاده‌سازی ناهمزمان است، از یک صف پردازش پیام استفاده کنید تا ordering و بسته‌بندی چندتکه‌ها (packaging) صحیح مدیریت شود.
* برای پیام‌هایی که بسته به سایز تبدیل به چند پکیج می‌شوند (مثلاً `getuserlist`, `getalllog`)، از پارامترهای `count`, `from`, `to` استفاده کنید و `stn` را برای شروع و ادامه بسته‌ها رعایت کنید.

---

# terminal-to-server.md

# پیام‌های Terminal → Server

این فایل شامل تمامی پیام‌هایی است که دستگاه (ترمینال) به سرور ارسال می‌کند.

---

## 1) Register — `cmd: "reg"`

**توضیح:** دستگاه برای ثبت‌نام/معرفی خود و ارسال اطلاعات سخت‌افزاری و ظرفیت‌ها به سرور پیام `reg` ارسال می‌کند.

**Request (Terminal → Server)**

```json
{
  "cmd": "reg",
  "sn": "ZX0006827500",
  "cpusn": "123456789",
  "devinfo": {
    "modelname": "tfs30",
    "usersize": 3000,
    "fpsize": 3000,
    "cardsize": 3000,
    "pwdsize": 3000,
    "logsize": 100000,
    "useduser": 1000,
    "usedfp": 1000,
    "usedcard": 2000,
    "usedpwd": 400,
    "usedlog": 100000,
    "usednewlog": 5000,
    "fpalgo": "thbio3.0",
    "firmware": "th600w v6.1",
    "time": "2016-03-25 13:49:30",
    "mac": "00-01-A9-01-00-01"
  }
}
```

**Response (Server → Terminal)**

```json
{
  "ret": "reg",
  "result": true,
  "cloudtime": "2016-03-25 13:49:30",
  "nosenduser": true
}
```

| Key               | Type   | Description          | Example               | Default  | Range/Limit                |
|-------------------|--------|----------------------|-----------------------|----------|----------------------------|
| cmd               | string | Command name         | "reg"                 | required | n/a                        |
| sn                | string | Serial number دستگاه | "ZX0006827500"        | required | unique                     |
| cpusn             | string | CPU serial           | "123456789"           | optional | n/a                        |
| devinfo.modelname | string | مدل دستگاه           | "tfs30"               | -        | -                          |
| devinfo.usersize  | int    | ظرفیت کاربر          | 3000                  | -        | 1000/3000/5000/...         |
| devinfo.fpalgo    | string | الگوریتم اثر انگشت   | "thbio3.0"            | -        | "thbio1.0"/"thbio3.0"      |
| devinfo.time      | string | زمان دستگاه          | "2016-03-25 13:49:30" | -        | format yyyy-MM-dd HH:mm:ss |

**نکات:**

* فیلد `nosenduser` در پاسخ به دستگاه می‌گوید که آیا سرور از دستگاه بخواهد اطلاعات کاربران جدید را خودکار ارسال کند یا خیر.

---

## 2) SendLog — `cmd: "sendlog"`

**توضیح:** دستگاه لاگ‌های ثبت تردد را به سرور ارسال می‌کند؛ ممکن است شامل تصویر Base64 یا دما (برای دستگاه‌های AI) باشد.

**Request**

```json
{
  "cmd": "sendlog",
  "sn": "zx12345678",
  "count": 2,
  "logindex": 10,
  "record": [
    {
      "enrollid": 1,
      "time": "2016-03-25 13:49:30",
      "mode": 0,
      "inout": 0,
      "event": 0,
      "temp": 36.5,
      "verifymode": 13,
      "image": "...base64..."
    },
    {
      "enrollid": 2,
      "time": "2016-03-25 13:49:30",
      "mode": 0,
      "inout": 0,
      "event": 1,
      "verifymode": 13,
      "temp": 36.5,
      "image": "...base64..."
    }
  ]
}
```

**Response (Server → Terminal)**

```json
{
  "ret": "sendlog",
  "result": true,
  "count": 2,
  "logindex": 10,
  "cloudtime": "2016-03-25 13:49:30",
  "access": 1,
  "message": "message"
}
```

| Key      | Type   | Description               | Example      | Default  | Range/Limit                        |
|----------|--------|---------------------------|--------------|----------|------------------------------------|
| cmd      | string | "sendlog"                 | -            | required | -                                  |
| sn       | string | serial number             | "zx12345678" | required | -                                  |
| count    | int    | تعداد رکوردها در این پیام | 2            | required | <= device packet limit (p.e. 1000) |
| logindex | int    | index لاگ (برای پیگیری)   | 10           | optional | -                                  |
| record   | array  | آرایه رکوردهای لاگ        | -            | required | -                                  |

**record fields**
| Key | Type | Description | Example | Default | Range/Limit |
| enrollid | int | شناسه کاربر | 1 | - | >=0 |
| time | string | زمان رخداد | "2016-03-25 13:49:30" | - | yyyy-MM-dd HH:mm:ss |
| mode | int | 0:fp 1:card 2:pwd 8:face | 0 | - | see doc |
| inout | int | 0:in 1:out | 0 | - | - |
| event | int | کد رخداد یا دکمه‌ها | 0 | - | 0..16 (custom) |
| verifymode | int | حالت تایید (مثلاً QR) | 13 | - | see verifymode |
| temp | float | دمای ثبت‌شده (در دستگاه‌های دما) | 36.5 | - | - |
| image | string | Base64 تصویر (در صورت وجود) | "..." | - | Base64 string |

**نکات:**

* وقتی `enrollid` == 0، رکورد احتمالاً وضعیت/رویدادی مربوط به در یا دستگاه است.
* `access` در پاسخ سرور می‌تواند نشان دهد آیا باید در باز شود یا خیر (`1` open, `0` not).

---

## 3) SendUser — `cmd: "senduser"`

**توضیح:** ارسال اطلاعات کاربر که با کیپد دستگاه اضافه یا تغییر یافته است.

**Fingerprint example (Request):**

```json
{
  "cmd": "senduser",
  "sn": "zx12345678",
  "enrollid": 1,
  "name": "chingzou",
  "backupnum": 0,
  "admin": 0,
  "record": "aabbcc..."
}
```

**Card example:**

```json
{
  "cmd": "senduser",
  "sn": "zx12345678",
  "enrollid": 1,
  "name": "chingzou",
  "backupnum": 11,
  "admin": 0,
  "record": 2352253
}
```

**Password example:**

```json
{
  "cmd": "senduser",
  "sn": "zx12345678",
  "enrollid": 1,
  "name": "chingzou",
  "backupnum": 10,
  "admin": 0,
  "record": "12345678"
}
```

**Response (Server → Terminal)**

```json
{
  "ret": "senduser",
  "result": true,
  "cloudtime": "2016-03-25 13:49:30"
}
```

| Key | Type | Description | Example | Default | Range/Limit |
| cmd | string | "senduser" | - | required | - |
| sn | string | serial | - | required | - |
| enrollid | int | user id | 1 | required | >=0 |
| name | string | user name | "chingzou" | optional | utf-8, max reasonable length |
| backupnum | int | نوع داده کاربر | 0 | required | 0..9,10,11,20..27,30..37,50 |
| admin | int | 0:normal 1:admin 2:super | 0 | 0 | 0..2 |
| record | string/int | محتوا بسته به backupnum (Base64 یا id یا pwd) | see example | - | size limits (see note)

**نکات:**

* برای THbio3.0 طول `record` برای فینگرپرینت محدود به *~1620* و برای THbio1.0 به *~1024* است.

---

# server-to-terminal.md

# پیام‌های Server → Terminal

این فایل شامل دستوراتی است که سرور به سمت دستگاه ارسال می‌کند.

---

## ساختار پاسخ کلی

بسیاری از پاسخ‌های دستگاه شامل:

```json
{
  "ret": "<cmd>",
  "sn": "<sn>",
  "result": true,
  "...": "..."
}
```

در صورت خطا:

```json
{
  "ret": "<cmd>",
  "result": false,
  "reason": 1
}
```

---

## 1) GetUserList — `cmd: "getuserlist"`

**توضیح:** درخواست لیست کاربران از دستگاه. می‌تواند چند بسته‌ای باشد.

**Request (Server → Terminal)**

```json
{
  "cmd": "getuserlist",
  "stn": true
}
```

**Response (Terminal → Server)**

```json
{
  "ret": "getuserlist",
  "sn": "zx12345678",
  "result": true,
  "count": 40,
  "from": 0,
  "to": 39,
  "record": [
    {
      "enrollid": 1,
      "admin": 0,
      "backupnum": 0
    },
    {
      "enrollid": 2,
      "admin": 1,
      "backupnum": 0
    }
  ]
}
```

| Key | Type | Description | Example | Default | Range/Limit |
| cmd | string | "getuserlist" | - | required | - |
| stn | bool | اگر بسته اول باشد true | true/false | required | - |
| ret | string | response cmd | "getuserlist" | - | - |
| count | int | تعداد رکورد در بسته | 40 | - | <=40 per packet |
| from | int | شروع ایندکس | 0 | - | - |
| to | int | پایان ایندکس | 39 | - | - |

**نکات:**

* دستگاهها تا 40 رکورد در هر بسته برمی‌گردانند (محدودیت دستگاه).
* اگر هیچ کاربری وجود نداشته باشد `count:0` و `record: []` بازگردانده می‌شود.

---

## 2) GetUserInfo — `cmd: "getuserinfo"`

**توضیح:** دریافت اطلاعات مشخص یک کاربر (fingerprint / photo / card / pwd)

**Request:**

```json
{
  "cmd": "getuserinfo",
  "sn": "zx12345678",
  "enrollid": 1,
  "backupnum": 0
}
```

**Response:**

```json
{
  "ret": "getuserinfo",
  "sn": "zx12345678",
  "result": true,
  "enrollid": 1,
  "name": "chingzou",
  "backupnum": 0,
  "admin": 0,
  "record": "aabbccddeeff..."
}
```

| Key | Type | Description | Example | Default | Range/Limit |
| cmd | string | "getuserinfo" | - | required | - |
| enrollid | int | شناسه کاربر | 1 | required | >=0 |
| backupnum | int | نوع داده | 0/10/11/50 | required | see backupnum |

---

## 3) SetUserInfo / Download user — `cmd: "setuserinfo"`

**توضیح:** سرور درخواست می‌کند که مشخصات یک کاربر روی دستگاه نوشته شود (دانلود کاربر به دستگاه).

**Request (Server → Terminal)**

Fingerprint:

```json
{
  "cmd": "setuserinfo",
  "enrollid": 1,
  "name": "chingzou",
  "backupnum": 0,
  "admin": 0,
  "record": "aabbcc..."
}
```

Photo:

```json
{
  "cmd": "setuserinfo",
  "enrollid": 1,
  "name": "chingzou",
  "backupnum": 50,
  "admin": 0,
  "record": "...base64..."
}
```

**Response:**

```json
{
  "ret": "setuserinfo",
  "result": true
}
```

| Key | Type | Description | Example | Default | Range/Limit |
| cmd | string | "setuserinfo" | - | required | - |
| enrollid | int | شناسه کاربر | 1 | required | >=0 |
| backupnum | int | نوع داده | 0/50 | required | see backupnum |
| record | string/int | داده مرتبط (Base64 یا id یا pwd) | ... | - | size limits |

---

## 4) DeleteUser — `cmd: "deleteuser"`

**Request:**

```json
{
  "cmd": "deleteuser",
  "enrollid": 1,
  "backupnum": 0
}
```

**Response:**

```json
{
  "ret": "deleteuser",
  "result": true
}
```

| Key | Type | Description | Example | Default | Range/Limit |
| enrollid | int | user id | 1 | required | >=0 |
| backupnum | int | 0..9 fp,10 pwd,11 card,12 all fp,13 all info | 0 | required | see mapping |

---

## 5) GetUserName / SetUserName

**Get (Request):**

```json
{
  "cmd": "getusername",
  "sn": "zx12345678",
  "enrollid": 1
}
```

**Get (Response):**

```json
{
  "ret": "getusername",
  "result": true,
  "record": "chingzou"
}
```

**Set (Request):**

```json
{
  "cmd": "setusername",
  "count": 2,
  "record": [
    {
      "enrollid": 1,
      "name": "chingzou"
    },
    {
      "enrollid": 2,
      "name": "chingzou2"
    }
  ]
}
```

**Set (Response):**

```json
{
  "ret": "setusername",
  "result": true
}
```

| Key | Type | Description | Example | Default | Range/Limit |
| count | int | تعداد اسامی در بسته | 50 max | - | <=50 |

---

## 6) EnableUser / DisableUser — `cmd: "enableuser"`

**Request Enable:**

```json
{
  "cmd": "enableuser",
  "enrollid": 1,
  "enflag": 1
}
```

**Request Disable:**

```json
{
  "cmd": "enableuser",
  "enrollid": 1,
  "enflag": 0
}
```

**Response:**

```json
{
  "ret": "enableuser",
  "sn": "zx12345678",
  "result": true
}
```

| Key | Type | Description | Example | Default | Range/Limit |
| enflag | int | 1:enable, 0:disable | 1 | - | 0/1 |

---

## 7) CleanAllUsers — `cmd: "cleanuser"`

**Request:** `{ "cmd": "cleanuser" }`

**Response:** `{ "ret": "cleanuser", "sn": "zx12345678", "result": true }`

---

## 8) GetNewLog — `cmd: "getnewlog"`

**Request:** `{ "cmd": "getnewlog", "stn": true }`

**Response:** شبیه `sendlog` ولی از سمت ترمینال و شامل `count`, `from`, `to`, `record`.

---

## 9) GetAllLog — `cmd: "getalllog"`

**Request example:**

```json
{
  "cmd": "getalllog",
  "stn": true,
  "from": "2018-11-01",
  "to": "2018-12-30"
}
```

**Response:** بسته‌ای از لاگ‌ها مشابه `getnewlog`.

---

## 10) CleanAllLogs — `cmd: "cleanlog"`

**Request:** `{ "cmd": "cleanlog" }`

**Response:** `{ "ret": "cleanlog", "sn": "zx12345678", "result": true }`

---

## 11) Initialize System — `cmd: "initsys"`

**توضیح:** پاک‌سازی همه‌ی کاربران و لاگ‌ها. تنظیمات تغییر نمی‌کند.

**Request:** `{ "cmd": "initsys" }`

**تذکر:** پس از دریافت، دستگاه آنی عمل می‌کند و ممکن است پاسخی ندهد.

---

## 12) Reboot — `cmd: "reboot"`

**Request:** `{ "cmd": "reboot" }`
**نکته:** دستگاه بلافاصله ریبوت می‌شود و معمولاً پاسخی ارسال نمی‌کند.

---

## 13) CleanAllAdmins — `cmd: "cleanadmin"`

**Request:** `{ "cmd": "cleanadmin" }`
**Response:** `{ "ret": "cleanadmin", "sn": "zx12345678", "result": true }`

---

## 14) SetTime — `cmd: "settime"`

**Request:**

```json
{
  "cmd": "settime",
  "cloudtime": "2016-03-25 13:49:30"
}
```

**Response:** `{ "ret": "settime", "sn": "zx12345678", "result": true }`

---

## 15) SetDevInfo — `cmd: "setdevinfo"`

**توضیح:** تنظیم پارامترهای عمومی دستگاه (volume, language, verifymode و...)

**Request مثال:**

```json
{
  "cmd": "setdevinfo",
  "deviceid": 1,
  "language": 0,
  "volume": 8,
  "screensaver": 0,
  "verifymode": 0,
  "sleep": 0,
  "userfpnum": 3,
  "loghint": 1000,
  "reverifytime": 0
}
```

**Response:** `{ "ret": "setdevinfo", "sn": "zx12345678", "result": true }`

| Key | Type | Description | Example | Default | Range/Limit |
| language | int | language code | 0 | 0 | see language enum |
| volume | int | 0~10 | 8 | 6 | 0..10 |
| userfpnum | int | fingerprints per user | 3 | 3 | 1..10 |

---

## 16) GetDevInfo — `cmd: "getdevinfo"`

**Request:** `{ "cmd": "getdevinfo" }`

**Response example:**

```json
{
  "ret": "getdevinfo",
  "sn": "zx12345678",
  "result": true,
  "deviceid": 1,
  "language": 0,
  "volume": 0,
  "screensaver": 0,
  "verifymode": 0,
  "sleep": 0,
  "userfpnum": 3,
  "loghint": 1000,
  "reverifytime": 0
}
```

---

## 17) OpenDoor — `cmd: "opendoor"`

**Request:**

```json
{
  "cmd": "opendoor",
  "doornum": 1
}
```

**Response:** `{ "ret": "opendoor", "sn": "zx12345678", "result": true }`

---

## 18) SetDevLock — `cmd: "setdevlock"`

**توضیح:** مجموعه‌ای از پارامترهای پیچیده دسترسی (dayzone, weekzone, lockgroup, tryalarm و...)

**Request (نمونه خلاصه):**

```json
{
  "cmd": "setdevlock",
  "opendelay": 5,
  "doorsensor": 0,
  "alarmdelay": 0,
  "threat": 0,
  "InputAlarm": 0,
  "antpass": 0,
  "interlock": 0,
  "mutiopen": 0,
  "tryalarm": 0,
  "tamper": 0,
  "wgformat": 0,
  "wgoutput": 0,
  "cardoutput": 0,
  "dayzone": [
    {
      "day": [
        {
          "section": "06:00~07:00"
        },
        {
          "section": "08:30~12:00"
        }
      ]
    }
  ],
  "weekzone": [
    {
      "week": [
        {
          "day": 1
        },
        {
          "day": 1
        },
        {
          "day": 1
        },
        {
          "day": 1
        },
        {
          "day": 1
        },
        {
          "day": 2
        },
        {
          "day": 2
        }
      ]
    }
  ],
  "lockgroup": [
    {
      "group": 1234
    },
    {
      "group": 126
    }
  ]
}
```

**Response:** `{ "ret": "setdevlock", "sn": "zx12345678", "result": true }`

**نکته:** توضیح کامل منطق dayzone/weekzone در فایل PDF اصلی موجود است و باید در سرور mapping صحیح بین user->weekzone->dayzone->sections پیاده شود.

---

## 19) GetDevLock — `cmd: "getdevlock"`

**Request:** `{ "cmd": "getdevlock" }`

**Response:** حاوی ساختار کامل lock config (مشابه پارامترهای setdevlock).

---

## 20) GetUserLock / SetUserLock / DeleteUserLock / CleanUserLock

* `getuserlock` — دریافت پارامترهای دسترسی یک کاربر
* `setuserlock` — تنظیم پارامترهای دسترسی برای لیستی از کاربران
* `deleteuserlock` — حذف پارامترهای دسترسی یک کاربر
* `cleanuserlock` — پاک‌سازی همه پارامترهای دسترسی

**نمونه GetUserLock Response:**

```json
{
  "ret": "getuserlock",
  "sn": "zx12345678",
  "result": true,
  "enrollid": 1,
  "weekzone": 1,
  "weekzone2": 1,
  "weekzone3": 1,
  "weekzone4": 1,
  "group": 1,
  "starttime": "2016-03-25 01:00:00",
  "endtime": "2099-03-25 23:59:00"
}
```

---

## 21) gettime

**Request:** `{ "cmd": "gettime" }`

**Response example:** `{ "ret": "gettime", "sn": "zx12345678", "time": "2022-11-09 19:31:49" }`

---

## 22) QR Code — `sendqrcode` / `sendqrcode` reply

**Terminal → Server (sendqrcode):**

```json
{
  "cmd": "sendqrcode",
  "sn": "AI07F123456",
  "record": "123456"
}
```

**Server reply example:**

```json
{
  "ret": "sendqrcode",
  "sn": "AI07F1234567",
  "result": true,
  "access": 1,
  "enrollid": 10,
  "username": "tom",
  "message": "ok",
  "voice": "ok"
}
```

---

## 23) Questionnaire & Holiday & UserProfile

* `getquestionnaire` / `setquestionnaire` — پارامترهای فرم/پرسشنامه
* `getholiday` / `setholiday` — تقویم تعطیلات
* `setuserprofile` / `getuserprofile` — اطلاعات پروفایل کاربر (متن تا 200 بایت)

**نمونه getquestionnaire response:**

```json
{
  "ret": "getquestionnaire",
  "sn": "zx12345678",
  "result": true,
  "title": "inout event",
  "voice": "please select",
  "errmsg": "please select",
  "radio": true,
  "optionflag": 0,
  "usequestion": false,
  "useschedule": false,
  "card": 0,
  "items": [
    "in",
    "out",
    "onduty",
    "offduty"
  ],
  "schedules": [
    "00:01-11:12*1",
    "11:30-12:30*3"
  ]
}
```

---

# fingerprint-enrollment.md

# ثبت اثر انگشت (Fingerprint Enrollment)

این بخش فرآیند کامل ثبت اثر انگشت کاربر را توضیح می‌دهد.

---

## نگاه کلی

فرآیند ثبت اثر انگشت یک فرآیند **تعاملی** و **چند مرحله‌ای** است:

1. سرور دستور `adduser` را ارسال می‌کند
2. دستگاه وارد حالت ثبت اثر انگشت می‌شود
3. کاربر باید **3 بار** انگشت خود را روی سنسور قرار دهد
4. سرور باید به صورت مداوم (polling) وضعیت را با `checkregstatus` بررسی کند
5. دستگاه status های مختلف را برمی‌گرداند (1, 2, 3, 100)
6. در صورت موفقیت، اثر انگشت در دستگاه ذخیره می‌شود

---

## مراحل پیاده‌سازی

### مرحله 1️⃣: شروع فرآیند ثبت (`adduser`)

**Request (Server → Terminal):**

```json
{
  "password": "1",
  "cmd": "adduser",
  "flag": 2,
  "enrollid": 2,
  "backupnum": 0
}
```

**Response (Terminal → Server):**

```json
{
  "ret": "adduser",
  "enrollid": 2,
  "sn": "ZPET14016833",
  "backupnum": 0,
  "result": true
}
```

| Key       | Type   | Description                                        | Example          | Required |
|-----------|--------|----------------------------------------------------|------------------|----------|
| password  | string | رمز عبور دستگاه                                    | "1"              | بله      |
| cmd       | string | نام دستور                                          | "adduser"        | بله      |
| flag      | int    | پرچم دستور (معمولاً 2)                             | 2                | بله      |
| enrollid  | int    | شناسه کاربر                                        | 2                | بله      |
| backupnum | int    | شماره slot اثر انگشت (0-9)                        | 0                | بله      |

**توضیحات:**

- `backupnum`: هر کاربر می‌تواند تا 10 اثر انگشت داشته باشد (0-9)
- `flag`: معمولاً مقدار 2 استفاده می‌شود
- بعد از این دستور، دستگاه منتظر می‌ماند تا کاربر انگشت را روی سنسور قرار دهد

---

### مرحله 2️⃣: نظارت بر وضعیت (`checkregstatus` - Polling)

بعد از ارسال `adduser`, باید به صورت مداوم (هر 1-2 ثانیه) وضعیت را بررسی کنیم:

**Request (Server → Terminal):**

```json
{
  "password": "1",
  "cmd": "checkregstatus"
}
```

**Responses (Terminal → Server):**

#### Status 1: اولین اسکن اثر انگشت

```json
{
  "ret": "checkregstatus",
  "sn": "ZPET14016833",
  "result": true,
  "status": 1,
  "msg": "ثبت اثرانگشت"
}
```

یا با تصویر:

```json
{
  "ret": "checkregstatus",
  "sn": "ZPET14016833",
  "result": true,
  "status": 1,
  "image": "/9j/4AAQSkZJRg..."
}
```

#### Status 2: دومین اسکن اثر انگشت

```json
{
  "ret": "checkregstatus",
  "sn": "ZPET14016833",
  "result": true,
  "status": 2,
  "msg": "ثبت مجدد اثرانگشت"
}
```

یا با تصویر:

```json
{
  "ret": "checkregstatus",
  "sn": "ZPET14016833",
  "result": true,
  "status": 2,
  "image": "/9j/4AAQSkZJRgABAQAAAQAB..."
}
```

#### Status 3: سومین اسکن اثر انگشت

```json
{
  "ret": "checkregstatus",
  "sn": "ZPET14016833",
  "result": true,
  "status": 3,
  "msg": "ثبت سوم اثرانگشت"
}
```

#### Status 100: ثبت موفقیت‌آمیز ✅

```json
{
  "ret": "checkregstatus",
  "sn": "ZPET14016833",
  "result": true,
  "status": 100,
  "msg": "ثبت موفق",
  "image": "/9j/4AAQSkZJ..."
}
```

بعد از دریافت `status: 100`، می‌توانید polling را متوقف کنید.

---

### مرحله 3️⃣: بررسی نهایی (`getuserinfo`)

بعد از ثبت موفق، می‌توانید اطلاعات کاربر را بررسی کنید:

**Request:**

```json
{
  "password": "1",
  "cmd": "getuserinfo",
  "enrollid": 2
}
```

**Response:**

```json
{
  "ret": "getuserinfo",
  "result": true,
  "sn": "ZPET14016833",
  "enrollid": 2,
  "name": "",
  "department": "",
  "admin": 0,
  "fpflag": 1,
  "fpcnt": 1,
  "enable": 1
}
```

| Key    | Type | Description                | Value |
|--------|------|----------------------------|-------|
| fpflag | int  | دارای اثر انگشت (1 = بله) | 1     |
| fpcnt  | int  | تعداد اثر انگشت ثبت شده   | 1     |

---

## جدول Status ها

| Status | توضیح فارسی                       | Polling   | Action                                      |
|--------|----------------------------------|-----------|---------------------------------------------|
| 1      | اولین بار اسکن اثر انگشت         | ادامه     | نمایش پیام "لطفاً مجدداً اسکن کنید"         |
| 2      | دومین بار اسکن اثر انگشت         | ادامه     | نمایش پیام "یک بار دیگر اسکن کنید"          |
| 3      | سومین بار اسکن اثر انگشت         | ادامه     | نمایش پیام "در حال تکمیل..."               |
| 100    | ثبت موفقیت‌آمیز کامل شده         | متوقف کن  | نمایش پیام "با موفقیت ثبت شد"              |
| < 0    | خطا                              | متوقف کن  | نمایش پیام خطا                              |

---

## نکات مهم

### ✅ نکات پیاده‌سازی:

1. **Polling**: هر 1-2 ثانیه `checkregstatus` را فراخوانی کنید
2. **سه مرحله‌ای**: دستگاه برای افزایش دقت، 3 بار از کاربر می‌خواهد اسکن کند
3. **تصاویر Base64**: در برخی status ها، تصویر اثر انگشت برگردانده می‌شود (اختیاری)
4. **پیام‌های فارسی**: فیلد `msg` حاوی پیام راهنمای فارسی است
5. **Stop Condition**: وقتی `status: 100` دریافت کردید، polling را متوقف کنید

### ⚠️ احتیاط‌های امنیتی:

- محدود کردن تعداد دفعات تلاش (مثلاً 5 بار)
- Timeout برای polling (مثلاً 60 ثانیه)
- لاگ گرفتن از همه مراحل برای audit

### 🎯 UX بهتر:

- نمایش Progress Bar (33% → 66% → 90% → 100%)
- نمایش تصویر اثر انگشت (اگر موجود باشد)
- صدای بیپ یا vibration برای feedback
- پیام‌های راهنما واضح و فارسی

---

## مثال کامل با Laravel Package

### استفاده در Controller:

```php
use Sajadsoft\BiometricDevices\Facades\BiometricDevice;
use Sajadsoft\BiometricDevices\Enums\BiometricType;

// شروع فرآیند ثبت اثر انگشت
BiometricDevice::enrollFingerprint(
    deviceSerial: 'ZPET14016833',
    employeeId: 2,
    biometricType: BiometricType::FINGERPRINT_1
);

// بعد از این، باید polling را شروع کنید:
// هر 1-2 ثانیه:
BiometricDevice::checkRegistrationStatus('ZPET14016833');
```

### دریافت وضعیت با Event Listener:

```php
// app/Listeners/HandleFingerprintEnrollment.php

namespace App\Listeners;

use Sajadsoft\BiometricDevices\Events\FingerprintEnrollmentStarted;
use Sajadsoft\BiometricDevices\Events\RegistrationStatusReceived;

class HandleFingerprintEnrollment
{
    public function handleStarted(FingerprintEnrollmentStarted $event)
    {
        // دستگاه وارد حالت ثبت شد
        Log::info('Enrollment started', [
            'device' => $event->deviceSerial,
            'employee' => $event->employeeId,
        ]);
        
        // شروع polling (می‌توانید از Queue یا Job استفاده کنید)
    }
    
    public function handleStatus(RegistrationStatusReceived $event)
    {
        $status = $event->status;
        
        // نمایش پیام به کاربر
        Log::info($status->getUserMessage(), [
            'progress' => $status->getProgressPercentage(),
        ]);
        
        if ($status->isComplete()) {
            // ثبت کامل شد - متوقف کردن polling
            Log::info('Enrollment completed successfully');
        }
        
        if ($status->hasFailed()) {
            // خطا رخ داده
            Log::error('Enrollment failed');
        }
    }
}

// در EventServiceProvider:
Event::listen(FingerprintEnrollmentStarted::class, [HandleFingerprintEnrollment::class, 'handleStarted']);
Event::listen(RegistrationStatusReceived::class, [HandleFingerprintEnrollment::class, 'handleStatus']);
```

### نمایش Real-Time با WebSocket:

```javascript
// Frontend (Vue/React/Livewire)

// شروع فرآیند
axios.post('/api/devices/enroll-fingerprint', {
    device_serial: 'ZPET14016833',
    employee_id: 2,
    fingerprint_slot: 0
});

// دریافت وضعیت real-time از WebSocket
Echo.channel('device.ZPET14016833')
    .listen('RegistrationStatusReceived', (event) => {
        const status = event.status;
        
        // بروزرسانی UI
        updateProgress(status.progress_percentage);
        showMessage(status.message);
        
        if (status.status === 100) {
            // موفق
            showSuccess('اثر انگشت با موفقیت ثبت شد');
            stopPolling();
        }
    });
```

---

## خلاصه Flow Chart

```
[Server] ──(adduser)──► [Device]
                          │
                          ▼
                    [حالت ثبت]
                          │
[Server] ──(polling)──► [Device]
    ▲                     │
    │                     ▼
    │              status: 1 (33%)
    │                     │
    │                     ▼
    │              status: 2 (66%)
    │                     │
    │                     ▼
    │              status: 3 (90%)
    │                     │
    └──────────────       ▼
                   status: 100 ✅
```

---

# خاتمه

این فایل‌ها شامل مستندات کامل پروتکل WebSocket + JSON دستگاه‌های حضور و غیاب هستند. بخش جدید **Fingerprint Enrollment** به تفصیل فرآیند ثبت اثر انگشت را توضیح می‌دهد.
