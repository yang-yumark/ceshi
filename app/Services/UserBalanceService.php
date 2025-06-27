<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class UserBalanceService
{
    public function updateBalance(User $user, float $amount, string $operation): void
    {
        // 使用数据库事务保证操作原子性
        DB::transaction(function () use ($user, $amount, $operation) {
            // 使用悲观锁防止并发更新
            $freshUser = $user->lockForUpdate()->fresh();

            match ($operation) {
                'increment' => $freshUser->increment('balance', $amount),
                'decrement' => $freshUser->decrement('balance', $amount),
                default => throw new \InvalidArgumentException("无效的操作类型: $operation")
            };

            // 余额不足验证
            if ($freshUser->balance < 0) {
                throw new \DomainException("用户余额不足");
            }
        }, 3); // 事务重试3次
    }
}