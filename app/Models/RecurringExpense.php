<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class RecurringExpense extends Model
{

    protected $fillable = [
        'user_id',
        'category_id',
        'amount',
        'title',
        'description',
        'recurring_frequency',
        'recurring_start_date',
        'recurring_end_date',
    ];

    protected $casts = [
        'recurring_start_date' => 'date',
        'recurring_end_date' => 'date',
        'is_auto_generated' => 'boolean',
        'amount' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function childExpenses()
    {
        return $this->hasMany(Expense::class, 'recurring_expense_id');
    }


    // Scopes
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }
    public function scopeActive($query)
    {
        return
            $query->where(function ($query) {
                $query->whereNull('recurring_end_date')
                    ->orWhere('recurring_end_date', '>=', now());

            })->where('recurring_start_date', '<=', now());
    }

    public function scopeFrequency($query, $frequency)
    {
        return $query->where('frequency', $frequency);
    }


    // Functions
    public function isActive()
    {
        return $this->recurring_end_date === null || $this->recurring_end_date->isAfter(now());
    }
    public function getRemainingDays(){
        if(! $this->isActive())
            return 0;
        if($this->recurring_end_date == null)
            return null ;

        return ceil(Carbon::now()->diffInDays($this->recurring_end_date));
    }
    public function totalGeneratedAmount()
    {
        return $this->childExpenses()->sum('amount');
    }

    // هل لازم اعمل حدث جديد
    public function shouldGenerateNextOccurrences()
    {
        if ($this->recurring_end_date && now()->isAfter($this->recurring_end_date))
            return false;

        return true;
    }

    // تاريخ الحدث التالي
    public function getNextOccurrenceDate()
    {

        $lastChildExpense = $this->childExpenses()->orderBy('date', 'desc')->first();

        $baseDate = ($lastChildExpense ? $lastChildExpense->date : $this->recurring_start_date);

        return match ($this->recurring_frequency) {
            'daily' => $baseDate->copy()->addDay(),
            'weekly' => $baseDate->copy()->addWeek(),
            'monthly' => $baseDate->copy()->addMonth(),
            'yearly' => $baseDate->copy()->addYear(),
            default => throw new \Exception('Invalid recurring frequency')
        };
    }
}
