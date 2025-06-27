// 创建队列表
php artisan queue:table
php artisan migrate

//  Supervisor 配置 (/etc/supervisor/conf.d/laravel-worker.conf)
[program:laravel-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/your/project/artisan queue:work database --queue=balance_updates --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=1 # 对于严格顺序的操作，建议单进程
redirect_stderr=true
stdout_logfile=/var/log/laravel-worker.log
stopwaitsecs=60

// 启动 Supervisor
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start laravel-worker:*

// 测试用例示例
<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Jobs\UpdateUserBalance;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Foundation\Testing\RefreshDatabase;

class UpdateUserBalanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_balance_update_with_unique_lock()
    {
        $user = User::factory()->create(['balance' => 100]);
        
        // 分发两个并发作业
        Bus::batch([
            new UpdateUserBalance($user, 50, 'decrement'),
            new UpdateUserBalance($user, 30, 'decrement'),
        ])->dispatch();
        
        // 等待作业完成
        $this->artisan('queue:work --once')->assertExitCode(0);
        $this->artisan('queue:work --once')->assertExitCode(0);
        
        $this->assertEquals(20, $user->fresh()->balance);
    }
    
    public function test_lock_timeout_retry()
    {
        $user = User::factory()->create(['balance' => 100]);
        
        // 手动获取锁模拟锁占用
        $lock = Cache::lock('balance_lock_'.$user->id, 10);
        $lock->acquire();
        
        // 分发作业
        $job = new UpdateUserBalance($user, 50, 'decrement');
        $job->handle();
        
        $lock->release();
        
        // 作业应重试而不是失败
        $this->assertEquals(50, $user->fresh()->balance);
    }
    
    public function test_insufficient_balance_exception()
    {
        $user = User::factory()->create(['balance' => 30]);
        
        $this->expectException(\DomainException::class);
        
        $job = new UpdateUserBalance($user, 50, 'decrement');
        $job->handle();
    }
}