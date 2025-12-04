@props([
    'from_to_color' => ' from-green-600 to-emerald-600 shadow-lg',
    'name' => 'default_name',
    'description' => 'default description',
    'withCalender' => false,
    'selectedYear' => now()->year,
    'selectedMonth' => now()->month,
    'previous_month_function_name' => 'prevMonth',
    'next_month_function_name' => 'nextMonth',
])


<div class=" border-accent rounded-t-2xl bg-gradient-to-r {{ $from_to_color }}">
    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between flex-wrap">
            <div class="">
                <h1 class="text-3xl font-bold text-white">{{ $name }}</h1>
                <p class="text-green-100 mt-1 ">
                    {{ $description }}
                </p>
            </div>
            <div>
                {{ $button ?? '' }}
            </div>
            @if ($withCalender)
                <div class="flex items-center gap-4">
                    {{-- calendar --}}
                    <button title="Previous Month" wire:click="{{ $previous_month_function_name }}"
                        class="p-2 bg-white/20 hover:bg-white/30 rounded-lg text-white transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                        </svg>
                    </button>
                    <button title="get current Date" wire:click="setCurrentMonth"
                        class="px-4 py-2 bg-white/20 hover:bg-white/30 rounded-lg text-white font-semibold transition">
                        {{ \Carbon\Carbon::create($selectedYear, $selectedMonth, 1)->format('F Y') }}
                    </button>
                    <button title="Next Month" wire:click="{{ $next_month_function_name }}"
                        class="p-2 bg-white/20 hover:bg-white/30 rounded-lg text-white transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </button>
                </div>
            @endif
        </div>
    </div>
</div>
