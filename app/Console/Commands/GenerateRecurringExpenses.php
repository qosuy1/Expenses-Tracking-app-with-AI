<?php

namespace App\Console\Commands;

use App\Models\Expense;
use Illuminate\Console\Command;

class GenerateRecurringExpenses extends Command
{
    protected $signature = 'expenses:generate-recurring';
    protected $description = 'Generate pending recurring expense occurrences';

    public function handle()
    {
        $this->info('Generating recurring expenses...');

        $recurringExpenses = Expense::where('type', 'recurring')
            ->whereNull('parent_expense_id') // Only parent expenses
            ->get();

        $totalGenerated = 0;

        foreach ($recurringExpenses as $expense) {
            $generated = $expense->generatePendingOccurrences();
            $totalGenerated += $generated;

            if ($generated > 0) {
                $this->info("Generated {$generated} occurrences for expense: {$expense->title}");
            }
        }

        $this->info("Total generated: {$totalGenerated} expense occurrences");

        return Command::SUCCESS;
    }
}