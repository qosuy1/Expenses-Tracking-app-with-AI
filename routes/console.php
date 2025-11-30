<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('expense:generate-recurring-expense')
    ->dailyAt('03:00')
    ->withoutOverlapping()
    ->onSuccess(function () {
        Log::info('Successfully executed expense:generate-recurring-expense command', [
            'command' => 'expense:generate-recurring-expense',
            'timestamp' => now(),
        ]);
    })->onFailure(function () {
        Log::error('Failed to execute expense:generate-recurring-expense command', [
            'command' => 'expense:generate-recurring-expense',
            'timestamp' => now(),
        ]);
    });
