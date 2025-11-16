<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Expense extends Model
{
    use SoftDeletes, HasFactory;

    protected $fillable = [
        'user_id',
        'category_id',
        'amount',
        'title',
        'description',
        'date',
        'type',
        'recurring_frequncy',
        'recurring_start_date',
        'recurring_end_date',
        'parent_expense_id',
        'is_auto_generated'
    ];

    protected $casts = [
        'recurring_start_date' => 'date',
        'recurring_end_date' => 'date',
        'date' => 'date',
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

    public function parentExpense()
    {
        return $this->belongsTo(Expense::class, 'parent_expense_id');
    }

    public function childExpenses()
    {
        return $this->hasMany(Expense::class, 'parent_expense_id');
    }

    #[Scope]
    public function forUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }
    #[Scope]
    public function recurring($query)
    {
        return $query->where('type', 'recurring');
    }
    #[Scope]
    public function oneTime($query)
    {
        return $query->where('type', 'one-time');
    }

    #[Scope]
    public function inMonth($query, $month, $year)
    {
        return $query->whereMonth('date', $month)->whereYear('date', $year);
    }

    #[Scope]
    public function inDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('date', [$startDate, $endDate]);
    }

    public function isRecurring()
    {
        return $this->type == 'recurring';
    }

    // هل لازم اعمل حدث جديد
    public function shouldGenerateNextOccurrences()
    {
        if ($this->isRecurring())
            return false;
        if ($this->recurring_end_date && now()->isAfter($this->recurring_end_date))
            return false;

        return true;
    }

    // تاريخ الحدث التالي
    public function getNextOccurrenceDate()
    {
        if (!$this->isRecurring())
            return null;
        $lastChildExpense = $this->childExpenses()->orderBy('date', 'desc')->first();

        $baseDate = ($lastChildExpense ? $lastChildExpense->date : $lastChildExpense->recurring_start_date);

        return match ($this->recurring_frequncy) {
            'daily' => $baseDate->copy()->addDay(),
            'weekly' => $baseDate->copy()->addWeek(),
            'monthly' => $baseDate->copy()->addMonth(),
            'yearly' => $baseDate->copy()->addYear(),
            default => throw new \Exception('Invalid recurring frequency')
        };
    }

}
