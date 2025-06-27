<?php

namespace App\Http\Controllers;

use App\Jobs\UpdateUserBalance;
use App\Models\User;

class UserController extends Controller
{
    public function updateBalance(User $user)
    {
        // 验证逻辑...
        $amount = request('amount');
        $operation = request('operation');

        UpdateUserBalance::dispatch($user, $amount, $operation)
            ->onQueue('balance_updates'); // 专用队列

        return response()->json(['message' => '余额更新已进入队列']);
    }
}