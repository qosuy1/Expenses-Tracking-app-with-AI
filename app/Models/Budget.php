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
        $query = Expense::forUser($this->user_id)
            ->whereYear('date', $this->year)
            ->whereMonth('date', $this->month);
            
        if ($this->category_id) {
            $query->where('category_id', $this->category_id);
        }else{
            $query->where('category_id', null);
        }
        return $query->sum('amount');
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
