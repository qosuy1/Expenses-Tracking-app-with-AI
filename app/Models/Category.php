<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $fillable = [
        'name',
        'user_id',
        'color',
        'icon',
    ];
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function expenses()
    {
        return $this->hasMany(Expense::class);
    }
    public function budgets()
    {
        return $this->hasMany(Budget::class);
    }

    // get total spent for a month

    public function getTotalSpentForMonth($month, $year):float
    {
        $totalSpent = 0;

        $totalSpent = $this->expenses()
            ->whereMonth('date', $month)
            ->whereYear('date', $year)
            ->sum('amount');

        return $totalSpent;
    }

    
}
