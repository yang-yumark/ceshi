<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\UserBalanceService;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Contracts\Cache\LockTimeoutException;

class UpdateUserBalance implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public float $amount,
        public string $operation = 'increment' // increment 或 decrement
    ) {}

    public function handle(UserBalanceService $service): void
    {
        $lock = Cache::lock('balance_update_'.$this->user->id, 10);

        try {
            $lock->block(5); // 最多等待5秒获取锁

            $service->updateBalance($this->user, $this->amount, $this->operation);

        } catch (LockTimeoutException $e) {
            $this->release(2); // 2秒后重试
        } finally {
            optional($lock)->release();
        }
    }

    // 自定义重试时间窗口
    public function retryUntil(): \DateTime
    {
        return now()->addMinutes(10);
    }
}