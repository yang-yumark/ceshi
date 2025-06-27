<?php

namespace App\Http\Controllers;

use App\Jobs\UpdateUserBalance;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserBalanceController extends Controller
{
    public function updateBalance(Request $request, User $user)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'operation' => ['required', Rule::in(['increment', 'decrement'])]
        ]);
        
        // 分发唯一作业到专用队列
        UpdateUserBalance::dispatch(
            $user, 
            $validated['amount'], 
            $validated['operation']
        )->onQueue('balance_updates');
        
        return response()->json([
            'message' => '余额更新已进入队列处理',
            'data' => [
                'user_id' => $user->id,
                'current_balance' => $user->balance
            ]
        ]);
    }
}