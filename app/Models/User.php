<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class User extends Authenticatable
{
    use HasFactory;

    protected $fillable = [
        'name', 'email', 'password', 'balance'
    ];

    protected $casts = [
        'balance' => 'decimal:2',
    ];
    
    // 范围方法用于锁定行
    public function scopeWithLock($query)
    {
        return $query->lockForUpdate();
    }
}