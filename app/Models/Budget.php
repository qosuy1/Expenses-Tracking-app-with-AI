<?php

namespace App\Models;

use Attribute;
use Illuminate\Database\Eloquent\Model;

class Budget extends Model
{
    protected $fillable = [
        'user_id',
        'category_id',
        'amount',
        'month',
        'year'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'month' => 'integer',
        'year' => 'integer'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function getSpentAmountAttribute()
    {
        // if we have budget for some category 
        if ($this->category_id) {
            return $this->category->getTotalSpentForMonth($this->month, $this->year);
        }
        // if we don't have a spesifice budget for some category
        return Expense::forUser(userId: $this->user)
            ->inMonth($this->month, $this->year)
            ->sum('amount');
    }


    public function getRemainingAmountAttribute()
    {
        return $this->amount - $this->spent_amount;
    }

    public function getPercentageUsed()
    {
        return ($this->spent_amount / $this->amount) * 100;
    }

    public function isOverBudget(): bool
    {
        return $this->spent_amount > $this->amount;
    }


}
