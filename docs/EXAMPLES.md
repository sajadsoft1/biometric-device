# نمونه‌های پیشرفته BiometricDevices

این فایل شامل نمونه‌های کاربردی و پیشرفته برای سناریوهای واقعی است.

## فهرست مطالب

1. [Integration با Queue Jobs](#integration-با-queue-jobs)
2. [Integration با Livewire](#integration-با-livewire)
3. [Custom Event Listeners](#custom-event-listeners)
4. [مدیریت خطاها](#مدیریت-خطاها)
5. [Testing](#testing)
6. [Multi-Club/Multi-Tenant](#multi-clubmulti-tenant)
7. [Dashboard و Monitoring](#dashboard-و-monitoring)

---

## Integration با Queue Jobs

### 1. اضافه کردن دسته‌ای کاربران

```php
// app/Jobs/AddUsersToDevice.php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Sajadsoft\BiometricDevices\Facades\BiometricDevice;
use Sajadsoft\BiometricDevices\DTOs\Commands\AddUserDTO;
use Sajadsoft\BiometricDevices\Enums\BiometricType;

class AddUsersToDevice implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 120;

    public function __construct(
        public string $deviceSerial,
        public array $users,
    ) {}

    public function handle(): void
    {
        foreach ($this->users as $user) {
            try {
                $dto = new AddUserDTO(
                    employeeId: $user['id'],
                    name: $user['name'],
                    biometricType: BiometricType::from($user['biometric_type']),
                    biometricData: $user['biometric_data'],
                    isAdmin: $user['is_admin'] ?? false
                );

                BiometricDevice::addUser($this->deviceSerial, $dto);
                
                // تاخیر کوتاه بین هر کاربر
                usleep(200000); // 200ms
                
            } catch (\Exception $e) {
                \Log::error('Failed to add user to device', [
                    'device' => $this->deviceSerial,
                    'employee_id' => $user['id'],
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    public function failed(\Exception $exception): void
    {
        \Log::error('AddUsersToDevice job failed', [
            'device' => $this->deviceSerial,
            'users_count' => count($this->users),
            'error' => $exception->getMessage(),
        ]);
    }
}

// استفاده:
$users = User::where('needs_sync', true)->get()->toArray();
AddUsersToDevice::dispatch('DEVICE_SERIAL', $users);
```

### 2. Sync کاربران به صورت دوره‌ای

```php
// app/Console/Commands/SyncUsersToDevices.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Device;
use App\Models\User;
use App\Jobs\AddUsersToDevice;

class SyncUsersToDevices extends Command
{
    protected $signature = 'devices:sync-users {--device=* : Device serials}';
    protected $description = 'Sync users to biometric devices';

    public function handle(): int
    {
        $deviceSerials = $this->option('device');
        
        $devices = empty($deviceSerials)
            ? Device::online()->get()
            : Device::whereIn('serial', $deviceSerials)->get();

        if ($devices->isEmpty()) {
            $this->error('No devices found');
            return 1;
        }

        $users = User::where('needs_biometric_sync', true)
            ->with('biometricData')
            ->get()
            ->map(fn($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'biometric_type' => $user->biometricData->type,
                'biometric_data' => $user->biometricData->data,
                'is_admin' => $user->is_admin,
            ])
            ->toArray();

        foreach ($devices as $device) {
            $this->info("Syncing {$users->count()} users to device {$device->serial}...");
            AddUsersToDevice::dispatch($device->serial, $users);
        }

        $this->info('Sync jobs dispatched successfully!');
        return 0;
    }
}

// در routes/console.php:
Schedule::command('devices:sync-users')->daily();
```

### 3. Job برای حذف کاربران غیرفعال

```php
// app/Jobs/RemoveInactiveUsersFromDevice.php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Sajadsoft\BiometricDevices\Facades\BiometricDevice;
use App\Models\User;

class RemoveInactiveUsersFromDevice implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $deviceSerial,
        public array $inactiveUserIds,
    ) {}

    public function handle(): void
    {
        foreach ($this->inactiveUserIds as $userId) {
            BiometricDevice::deleteUser(
                deviceSerial: $this->deviceSerial,
                employeeId: $userId,
                biometricType: null // حذف همه
            );
            
            \Log::info("Removed user {$userId} from device {$this->deviceSerial}");
            usleep(200000);
        }
    }
}
```

---

## Integration با Livewire

### 1. Component مدیریت دستگاه‌ها

```php
// app/Livewire/DeviceManager.php

namespace App\Livewire;

use Livewire\Component;
use Sajadsoft\BiometricDevices\Models\Device;
use Sajadsoft\BiometricDevices\Facades\BiometricDevice;

class DeviceManager extends Component
{
    public $devices = [];
    public $selectedDevice = null;

    protected $listeners = [
        'deviceStatusUpdated' => 'refreshDevices',
        'echo:devices,DeviceConnected' => 'handleDeviceConnected',
        'echo:devices,DeviceDisconnected' => 'handleDeviceDisconnected',
    ];

    public function mount()
    {
        $this->refreshDevices();
    }

    public function refreshDevices()
    {
        $this->devices = Device::with(['successCommands', 'failedCommands'])
            ->get()
            ->toArray();
    }

    public function selectDevice($serial)
    {
        $this->selectedDevice = Device::where('serial', $serial)->first();
    }

    public function getDeviceInfo($serial)
    {
        BiometricDevice::getDeviceInfo($serial);
        session()->flash('message', 'درخواست اطلاعات دستگاه ارسال شد');
    }

    public function openDoor($serial, $doorNumber = 1)
    {
        BiometricDevice::openDoor($serial, $doorNumber);
        session()->flash('message', "درب {$doorNumber} باز شد");
    }

    public function rebootDevice($serial)
    {
        if (confirm('آیا مطمئن هستید؟')) {
            BiometricDevice::reboot($serial);
            session()->flash('message', 'دستگاه در حال راه‌اندازی مجدد است');
        }
    }

    public function handleDeviceConnected($event)
    {
        $this->refreshDevices();
        session()->flash('success', "دستگاه {$event['deviceSerial']} متصل شد");
    }

    public function handleDeviceDisconnected($event)
    {
        $this->refreshDevices();
        session()->flash('warning', "دستگاه {$event['deviceSerial']} قطع شد");
    }

    public function render()
    {
        return view('livewire.device-manager');
    }
}
```

### 2. View برای Component

```blade
{{-- resources/views/livewire/device-manager.blade.php --}}

<div class="p-6">
    <h2 class="text-2xl font-bold mb-4">مدیریت دستگاه‌ها</h2>

    @if (session()->has('message'))
        <div class="bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 rounded mb-4">
            {{ session('message') }}
        </div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        @foreach($devices as $device)
            <div class="border rounded-lg p-4 {{ $device['is_online'] ? 'bg-green-50' : 'bg-gray-50' }}">
                <div class="flex items-center justify-between mb-2">
                    <h3 class="font-bold">{{ $device['name'] }}</h3>
                    <span class="px-2 py-1 text-xs rounded {{ $device['is_online'] ? 'bg-green-500 text-white' : 'bg-gray-500 text-white' }}">
                        {{ $device['is_online'] ? 'آنلاین' : 'آفلاین' }}
                    </span>
                </div>
                
                <p class="text-sm text-gray-600 mb-3">{{ $device['serial'] }}</p>
                
                <div class="flex gap-2">
                    <button 
                        wire:click="getDeviceInfo('{{ $device['serial'] }}')"
                        class="px-3 py-1 bg-blue-500 text-white rounded text-sm"
                        @if(!$device['is_online']) disabled @endif>
                        اطلاعات
                    </button>
                    
                    <button 
                        wire:click="openDoor('{{ $device['serial'] }}', 1)"
                        class="px-3 py-1 bg-green-500 text-white rounded text-sm"
                        @if(!$device['is_online']) disabled @endif>
                        باز کردن درب
                    </button>
                    
                    <button 
                        wire:click="rebootDevice('{{ $device['serial'] }}')"
                        class="px-3 py-1 bg-red-500 text-white rounded text-sm"
                        @if(!$device['is_online']) disabled @endif>
                        ریبوت
                    </button>
                </div>
            </div>
        @endforeach
    </div>
</div>
```

### 3. Component برای نمایش حضور و غیاب Real-time

```php
// app/Livewire/AttendanceLiveLog.php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Attendance;

class AttendanceLiveLog extends Component
{
    public $attendances = [];
    public $limit = 20;

    protected $listeners = [
        'echo:attendance,AttendanceReceived' => 'handleNewAttendance',
    ];

    public function mount()
    {
        $this->loadRecentAttendances();
    }

    public function loadRecentAttendances()
    {
        $this->attendances = Attendance::with('employee')
            ->latest()
            ->limit($this->limit)
            ->get()
            ->toArray();
    }

    public function handleNewAttendance($event)
    {
        // اضافه کردن به اول لیست
        array_unshift($this->attendances, [
            'employee_id' => $event['employeeId'],
            'employee_name' => $event['employeeName'],
            'check_time' => $event['timestamp'],
            'is_check_in' => $event['isCheckIn'],
            'device_serial' => $event['deviceSerial'],
        ]);

        // محدود کردن تعداد
        $this->attendances = array_slice($this->attendances, 0, $this->limit);

        // اضافه کردن notification
        $this->dispatch('attendance-received', [
            'name' => $event['employeeName'],
            'type' => $event['isCheckIn'] ? 'ورود' : 'خروج',
        ]);
    }

    public function render()
    {
        return view('livewire.attendance-live-log');
    }
}
```

---

## Custom Event Listeners

### 1. ارسال نوتیفیکیشن هنگام حضور VIP

```php
// app/Listeners/NotifyVipAttendance.php

namespace App\Listeners;

use Sajadsoft\BiometricDevices\Events\AttendanceReceived;
use App\Models\User;
use App\Notifications\VipAttendanceNotification;

class NotifyVipAttendance
{
    public function handle(AttendanceReceived $event): void
    {
        $dto = $event->attendance;
        
        // بررسی آیا کاربر VIP است
        $user = User::where('employee_id', $dto->employeeId)->first();
        
        if ($user && $user->is_vip) {
            // ارسال نوتیفیکیشن به مدیران
            $admins = User::where('is_admin', true)->get();
            
            foreach ($admins as $admin) {
                $admin->notify(new VipAttendanceNotification(
                    employeeName: $dto->employeeName,
                    checkTime: $dto->timestamp,
                    isCheckIn: $dto->isCheckIn,
                    deviceSerial: $dto->deviceSerial,
                ));
            }
            
            \Log::info('VIP attendance detected', [
                'employee_id' => $dto->employeeId,
                'name' => $dto->employeeName,
            ]);
        }
    }
}
```

### 2. ذخیره عکس حضور در Storage

```php
// app/Listeners/SaveAttendancePhoto.php

namespace App\Listeners;

use Sajadsoft\BiometricDevices\Events\AttendanceReceived;
use Illuminate\Support\Facades\Storage;

class SaveAttendancePhoto
{
    public function handle(AttendanceReceived $event): void
    {
        $dto = $event->attendance;
        
        // اگر عکس وجود دارد
        if ($dto->photoBase64) {
            $photoData = base64_decode($dto->photoBase64);
            
            $filename = sprintf(
                'attendance-photos/%s/%s_%s.jpg',
                $dto->timestamp->format('Y-m-d'),
                $dto->employeeId,
                $dto->timestamp->format('His')
            );
            
            Storage::put($filename, $photoData);
            
            \Log::info('Attendance photo saved', [
                'employee_id' => $dto->employeeId,
                'filename' => $filename,
            ]);
        }
    }
}
```

### 3. بررسی دمای بدن و ارسال هشدار

```php
// app/Listeners/CheckTemperatureAlert.php

namespace App\Listeners;

use Sajadsoft\BiometricDevices\Events\AttendanceReceived;
use App\Notifications\HighTemperatureAlert;
use App\Models\User;

class CheckTemperatureAlert
{
    public function handle(AttendanceReceived $event): void
    {
        $dto = $event->attendance;
        
        // اگر دمای بدن بالاتر از حد مجاز است
        if ($dto->temperature && $dto->temperature >= 37.5) {
            \Log::warning('High temperature detected', [
                'employee_id' => $dto->employeeId,
                'temperature' => $dto->temperature,
            ]);
            
            // ارسال هشدار به مسئولین
            $admins = User::where('is_admin', true)->get();
            \Notification::send($admins, new HighTemperatureAlert(
                employeeName: $dto->employeeName,
                temperature: $dto->temperature,
                timestamp: $dto->timestamp,
            ));
            
            // لاگ در جدول مخصوص
            \App\Models\TemperatureAlert::create([
                'employee_id' => $dto->employeeId,
                'temperature' => $dto->temperature,
                'check_time' => $dto->timestamp,
                'device_serial' => $dto->deviceSerial,
            ]);
        }
    }
}
```

---

## مدیریت خطاها

### 1. Retry Logic برای دستورات ناموفق

```php
// app/Console/Commands/RetryFailedCommands.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Sajadsoft\BiometricDevices\Models\DeviceCommand;
use Sajadsoft\BiometricDevices\Enums\DeviceCommandStatusEnum;

class RetryFailedCommands extends Command
{
    protected $signature = 'devices:retry-failed {--max-attempts=3}';
    protected $description = 'Retry failed device commands';

    public function handle(): int
    {
        $maxAttempts = $this->option('max-attempts');
        
        $failedCommands = DeviceCommand::where('status', DeviceCommandStatusEnum::FAILED)
            ->where('error_count', '<', $maxAttempts)
            ->where('created_at', '>=', now()->subHours(24))
            ->get();

        if ($failedCommands->isEmpty()) {
            $this->info('No failed commands to retry');
            return 0;
        }

        $this->info("Found {$failedCommands->count()} failed commands");

        foreach ($failedCommands as $command) {
            // بررسی اینکه دستگاه آنلاین است
            if (!$command->device->isOnline()) {
                $this->warn("Device {$command->device->serial} is offline, skipping");
                continue;
            }

            // تبدیل به pending برای ارسال مجدد
            $command->update([
                'status' => DeviceCommandStatusEnum::PENDING,
                'send_status' => false,
                'error_message' => null,
            ]);

            $this->info("Retrying command #{$command->id}: {$command->command_name}");
        }

        $this->info('Retry completed!');
        return 0;
    }
}
```

### 2. Monitoring و Alert برای دستورات

```php
// app/Console/Commands/MonitorDeviceCommands.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Sajadsoft\BiometricDevices\Models\DeviceCommand;
use Sajadsoft\BiometricDevices\Enums\DeviceCommandStatusEnum;
use App\Notifications\CommandFailureAlert;
use App\Models\User;

class MonitorDeviceCommands extends Command
{
    protected $signature = 'devices:monitor';
    protected $description = 'Monitor device commands and send alerts';

    public function handle(): int
    {
        // بررسی دستورات pending قدیمی
        $oldPendingCommands = DeviceCommand::where('status', DeviceCommandStatusEnum::PENDING)
            ->where('created_at', '<', now()->subMinutes(5))
            ->count();

        if ($oldPendingCommands > 0) {
            $this->warn("Found {$oldPendingCommands} old pending commands");
            
            $admins = User::where('is_admin', true)->get();
            \Notification::send($admins, new CommandFailureAlert(
                message: "{$oldPendingCommands} دستور در انتظار ارسال هستند",
                count: $oldPendingCommands,
            ));
        }

        // بررسی نرخ خطا در 1 ساعت گذشته
        $totalCommands = DeviceCommand::where('created_at', '>=', now()->subHour())->count();
        $failedCommands = DeviceCommand::where('status', DeviceCommandStatusEnum::FAILED)
            ->where('created_at', '>=', now()->subHour())
            ->count();

        if ($totalCommands > 0) {
            $failureRate = ($failedCommands / $totalCommands) * 100;
            
            if ($failureRate > 20) {
                $this->error("High failure rate: {$failureRate}%");
                
                $admins = User::where('is_admin', true)->get();
                \Notification::send($admins, new CommandFailureAlert(
                    message: "نرخ خطای بالا: {$failureRate}%",
                    count: $failedCommands,
                ));
            }
        }

        $this->info('Monitoring completed');
        return 0;
    }
}

// در routes/console.php:
Schedule::command('devices:monitor')->everyFiveMinutes();
```

---

## Testing

### 1. Unit Test برای DTO

```php
// tests/Unit/AddUserDTOTest.php

namespace Tests\Unit;

use Tests\TestCase;
use Sajadsoft\BiometricDevices\DTOs\Commands\AddUserDTO;
use Sajadsoft\BiometricDevices\Enums\BiometricType;

class AddUserDTOTest extends TestCase
{
    public function test_can_create_add_user_dto()
    {
        $dto = new AddUserDTO(
            employeeId: 1001,
            name: 'Test User',
            biometricType: BiometricType::FINGERPRINT_0,
            biometricData: 'BASE64_DATA',
            isAdmin: false
        );

        $this->assertEquals(1001, $dto->employeeId);
        $this->assertEquals('Test User', $dto->name);
        $this->assertEquals(BiometricType::FINGERPRINT_0, $dto->biometricType);
        $this->assertFalse($dto->isAdmin);
    }

    public function test_dto_to_array()
    {
        $dto = new AddUserDTO(
            employeeId: 1001,
            name: 'Test User',
            biometricType: BiometricType::FACE_0,
            biometricData: 'BASE64_DATA',
            isAdmin: true
        );

        $array = $dto->toArray();

        $this->assertArrayHasKey('employee_id', $array);
        $this->assertArrayHasKey('name', $array);
        $this->assertArrayHasKey('biometric_type', $array);
        $this->assertArrayHasKey('is_admin', $array);
        $this->assertTrue($array['is_admin']);
    }
}
```

### 2. Feature Test برای Events

```php
// tests/Feature/AttendanceEventTest.php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\Event;
use Sajadsoft\BiometricDevices\Events\AttendanceReceived;
use Sajadsoft\BiometricDevices\DTOs\Responses\AttendanceDTO;
use Sajadsoft\BiometricDevices\Enums\VerificationMode;
use Carbon\Carbon;

class AttendanceEventTest extends TestCase
{
    public function test_attendance_event_is_fired()
    {
        Event::fake();

        $dto = new AttendanceDTO(
            employeeId: 1001,
            employeeName: 'Test User',
            timestamp: Carbon::now(),
            verificationType: VerificationMode::FINGERPRINT,
            isCheckIn: true,
            deviceSerial: 'TEST_DEVICE',
            photoBase64: null,
            eventType: null,
            rawData: []
        );

        event(new AttendanceReceived($dto));

        Event::assertDispatched(AttendanceReceived::class, function ($event) use ($dto) {
            return $event->attendance->employeeId === $dto->employeeId;
        });
    }
}
```

### 3. Mock Test برای Driver

```php
// tests/Feature/DeviceCommandTest.php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Sajadsoft\BiometricDevices\Facades\BiometricDevice;
use Sajadsoft\BiometricDevices\DTOs\Commands\AddUserDTO;
use Sajadsoft\BiometricDevices\Enums\BiometricType;
use Sajadsoft\BiometricDevices\Models\Device;
use Sajadsoft\BiometricDevices\Models\DeviceCommand;

class DeviceCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_add_user_command_is_saved()
    {
        // ایجاد دستگاه
        $device = Device::factory()->create([
            'serial' => 'TEST_DEVICE',
            'is_online' => true,
        ]);

        // ارسال دستور
        $dto = new AddUserDTO(
            employeeId: 1001,
            name: 'Test User',
            biometricType: BiometricType::FINGERPRINT_0,
            biometricData: 'BASE64_DATA',
            isAdmin: false
        );

        BiometricDevice::addUser('TEST_DEVICE', $dto);

        // بررسی ذخیره در دیتابیس
        $this->assertDatabaseHas('device_commands', [
            'device_id' => $device->id,
            'command_name' => 'setuserinfo',
        ]);

        $command = DeviceCommand::where('device_id', $device->id)->first();
        $this->assertNotNull($command);
        $this->assertEquals('setuserinfo', $command->command_name);
    }
}
```

---

## Multi-Club/Multi-Tenant

### 1. مدل Device با پشتیبانی Multi-Club

```php
// app/Models/Device.php

namespace App\Models;

use Sajadsoft\BiometricDevices\Models\Device as BaseDevice;

class Device extends BaseDevice
{
    protected $fillable = [
        'club_id',
        'device_type',
        'location',
        'status',
        // ... سایر فیلدهای پکیج
    ];

    public function club()
    {
        return $this->belongsTo(Club::class);
    }

    public function scopeForClub($query, $clubId)
    {
        return $query->where('club_id', $clubId);
    }

    public function scopeForCurrentClub($query)
    {
        return $query->where('club_id', auth()->user()->club_id);
    }
}

// در config/biometric-devices.php:
'models' => [
    'device' => \App\Models\Device::class,
],
```

### 2. Listener با پشتیبانی Multi-Tenant

```php
// app/Listeners/SaveAttendanceMultiTenant.php

namespace App\Listeners;

use Sajadsoft\BiometricDevices\Events\AttendanceReceived;
use App\Models\Attendance;
use App\Models\Device;

class SaveAttendanceMultiTenant
{
    public function handle(AttendanceReceived $event): void
    {
        $dto = $event->attendance;
        
        // پیدا کردن دستگاه و club آن
        $device = Device::where('serial', $dto->deviceSerial)->first();
        
        if (!$device) {
            \Log::warning('Device not found', ['serial' => $dto->deviceSerial]);
            return;
        }

        // ذخیره attendance با club_id
        Attendance::create([
            'club_id' => $device->club_id,
            'device_id' => $device->id,
            'employee_id' => $dto->employeeId,
            'employee_name' => $dto->employeeName,
            'check_time' => $dto->timestamp,
            'is_check_in' => $dto->isCheckIn,
            'verification_type' => $dto->verificationType->value,
        ]);
    }
}
```

---

## Dashboard و Monitoring

### 1. Controller برای Dashboard

```php
// app/Http/Controllers/DeviceDashboardController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Sajadsoft\BiometricDevices\Models\Device;
use Sajadsoft\BiometricDevices\Models\DeviceCommand;
use App\Models\Attendance;
use Carbon\Carbon;

class DeviceDashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'total_devices' => Device::count(),
            'online_devices' => Device::online()->count(),
            'offline_devices' => Device::offline()->count(),
            'today_attendance' => Attendance::whereDate('check_time', today())->count(),
            'pending_commands' => DeviceCommand::pending()->count(),
            'failed_commands' => DeviceCommand::failed()
                ->where('created_at', '>=', now()->subDay())
                ->count(),
        ];

        $devices = Device::with(['successCommands', 'failedCommands'])
            ->withCount([
                'commands as pending_count' => fn($q) => $q->pending(),
                'commands as failed_count' => fn($q) => $q->failed(),
            ])
            ->get();

        $recentAttendance = Attendance::with('employee')
            ->latest()
            ->limit(50)
            ->get();

        return view('dashboard.devices', compact('stats', 'devices', 'recentAttendance'));
    }

    public function deviceDetails($deviceId)
    {
        $device = Device::with(['commands' => fn($q) => $q->latest()->limit(100)])
            ->findOrFail($deviceId);

        $commandStats = [
            'total' => $device->commands()->count(),
            'pending' => $device->pendingCommands()->count(),
            'success' => $device->successCommands()->count(),
            'failed' => $device->failedCommands()->count(),
        ];

        $todayAttendance = Attendance::where('device_id', $deviceId)
            ->whereDate('check_time', today())
            ->count();

        return view('dashboard.device-details', compact('device', 'commandStats', 'todayAttendance'));
    }

    public function commandHistory(Request $request)
    {
        $query = DeviceCommand::with('device')->latest();

        if ($request->filled('device_id')) {
            $query->where('device_id', $request->device_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        $commands = $query->paginate(50);

        return view('dashboard.command-history', compact('commands'));
    }
}
```

### 2. API برای Dashboard Real-time

```php
// routes/api.php

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/devices/status', function () {
        return [
            'devices' => Device::with('club')->get()->map(fn($device) => [
                'id' => $device->id,
                'serial' => $device->serial,
                'name' => $device->name,
                'is_online' => $device->is_online,
                'last_connected' => $device->last_connected_at,
                'club' => $device->club->name ?? null,
            ]),
            'stats' => [
                'online' => Device::online()->count(),
                'offline' => Device::offline()->count(),
                'pending_commands' => DeviceCommand::pending()->count(),
            ],
        ];
    });

    Route::get('/attendance/live', function () {
        return Attendance::with('employee')
            ->latest()
            ->limit(20)
            ->get();
    });
});
```

---

## نتیجه‌گیری

این نمونه‌ها شامل:
- ✅ Integration با Queue Jobs برای عملیات سنگین
- ✅ Integration با Livewire برای UI Real-time
- ✅ Custom Event Listeners برای پردازش‌های پیشرفته
- ✅ مدیریت خطاها و Retry Logic
- ✅ Testing کامل
- ✅ پشتیبانی از Multi-Club/Multi-Tenant
- ✅ Dashboard و Monitoring

برای سوالات بیشتر، به [README اصلی](../README.md) و [راهنمای استفاده](USAGE.md) مراجعه کنید.

---

**توسعه دهنده:** با ❤️ برای جامعه Laravel  
**نسخه:** 1.0.0  
**تاریخ بروزرسانی:** 2025-01-28

