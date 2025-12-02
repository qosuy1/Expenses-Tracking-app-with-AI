<?php

namespace App\Console\Commands;

use App\Models\Expense;
use App\Models\RecurringExpense;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GenerateRecurringExpense extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'expense:generate-recurring-expense';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate recurring expenses based on their schedule';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Generating recurring expenses...');

        // $recurringExpenses = Expense::recurring()
        //     ->whereNull('deleted_at')
        //     ->where('recurring_start_date', '<=', now())
        //     ->where(function ($query) {
        //         $query->whereNull('recurring_end_date')
        //             ->orWhere('recurring_end_date', '>=', now());
        //     })
        //     ->get();

        $recurringExpenses = RecurringExpense::active()->get();
        $generatedCount = 0;

        foreach ($recurringExpenses as $expense) {
            // Check if an expense for the current period already exists
            // $this->info("Processing Recurring Expense ID: {$expense->id}, Title: {$expense->title}");
            $generated = $this->generateExpenseForRecurring($expense);
            $generatedCount += $generated;
        }
        // $this->info($recurringExpenses);
        $this->info("Generated {$generatedCount} recurring expenses.");

        Log::info("Generated {$generatedCount} recurring expenses", [
            'command' => 'expenses:generate-recurring',
            'timestamp' => now(),
        ]);

        return Command::SUCCESS;
    }

    private function generateExpenseForRecurring(RecurringExpense $expense)
    {
        if (!$expense->shouldGenerateNextOccurrences()) {
            $this->info('No occurrences to generate.');
            return 0;
        }

        $generatedCount = 0;
        $nextDate = $expense->getNextOccurrenceDate();

        //  Generate all missing occurrences up to today (lte -> [before])
        while ($nextDate && $nextDate->lte(now()) && ($expense->recurring_end_date === null || $nextDate->lte($expense->recurring_end_date))) {
            // Check if an expense for this date already exists
            $exists = $expense->childExpenses()
                ->whereDate('date', $nextDate->toDateString())
                ->exists();
            // $this->info("Checking existence for date: {$nextDate->toDateString()} - Exists: " . ($exists ? 'Yes' : 'No'));

            if (!$exists) {
                // Create the new expense
                $this->createExpenseOccurrence($expense, $nextDate);
                $generatedCount++;
                $this->line("Generated: {$expense->title} for {$nextDate->format('Y-m-d')}");
            }

            // Move to the next occurrence
            $nextDate = match ($expense->recurring_frequency) {
                'daily' => $nextDate->copy()->addDay(),
                'weekly' => $nextDate->copy()->addWeek(),
                'monthly' => $nextDate->copy()->addMonth(),
                'yearly' => $nextDate->copy()->addYear(),
                default => null,
            };
        }

        return $generatedCount;
    }

    private function createExpenseOccurrence(RecurringExpense $expense, $newDate)
    {
        return Expense::create([
            'user_id' => $expense->user_id,
            'category_id' => $expense->category_id,
            'amount' => $expense->amount,
            'title' => $expense->title,
            'description' => $expense->description,
            'date' => $newDate->toDateString(),
            // 'type' => 'one-time',
            'recurring_expense_id' => $expense->id,
            'is_auto_generated' => true,
        ]);
    }
}
