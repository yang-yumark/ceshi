<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Contracts\Cache\LockTimeoutException;

class UpdateUserBalance implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3; // 最大尝试次数
    public $maxExceptions = 1; // 最大异常次数
    public $timeout = 30; // 超时时间(秒)

    public function __construct(
        public User $user,
        public float $amount,
        public string $operation = 'increment' // 'increment' 或 'decrement'
    ) {}

    // 定义作业唯一性：基于用户ID
    public function uniqueId(): string
    {
        return 'balance_update_'.$this->user->id;
    }

    // 定义唯一锁有效期
    public function uniqueFor(): int
    {
        return 60; // 60秒
    }

    public function handle(): void
    {
        // 使用原子锁确保操作唯一性 (官方推荐方式)
        $lock = Cache::lock('balance_lock_'.$this->user->id, 15);
        
        try {
            // 尝试获取锁，最多等待5秒
            $lock->block(5);
            
            // 在事务中执行余额更新
            DB::transaction(function () {
                // 重新加载用户模型并锁定行
                $freshUser = $this->user->fresh()->lockForUpdate();
                
                // 执行余额操作
                match ($this->operation) {
                    'increment' => $freshUser->increment('balance', $this->amount),
                    'decrement' => $this->decrementBalance($freshUser, $this->amount),
                    default => throw new \InvalidArgumentException("无效的操作类型: {$this->operation}")
                };
            });
            
        } catch (LockTimeoutException $e) {
            // 获取锁超时，延迟重试
            $this->release(10); // 10秒后重试
        } finally {
            // 确保释放锁
            optional($lock)->release();
        }
    }
    
    protected function decrementBalance(User $user, float $amount): void
    {
        if ($user->balance < $amount) {
            throw new \DomainException("用户余额不足");
        }
        
        $user->decrement('balance', $amount);
    }
    
    // 定义重试策略
    public function retryUntil(): \DateTime
    {
        return now()->addMinutes(10);
    }
    
    // 处理失败任务
    public function failed(\Throwable $exception): void
    {
        // 记录失败原因或发送通知
        \Log::error("用户余额更新失败: {$exception->getMessage()}", [
            'user_id' => $this->user->id,
            'amount' => $this->amount,
            'operation' => $this->operation
        ]);
    }
}