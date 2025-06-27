<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserBalanceController;

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/users/{user}/balance', [UserBalanceController::class, 'updateBalance'])
        ->name('users.balance.update');
});