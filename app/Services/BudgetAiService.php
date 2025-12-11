<?php

namespace App\Services;

use Carbon\Carbon;
use App\Models\Expense;
use App\Models\Category;
use Gemini\Laravel\Facades\Gemini;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class BudgetAiService
{
    public $categoryId = '';
    public $userId = '';
    public $month = "";
    public $year = "";
    private $is_AI_generated_content_successfully = false;
    private $historicalData;

    public function __construct(?int $categoryId, ?int $userId, ?int $month, ?int $year)
    {
        $this->categoryId = $categoryId == 0 ? null : $categoryId;
        $this->userId = $userId ?? Auth::id();
        $this->month = $month ?? "";
        $this->year = $year ?? "";
    }
    public function getBudgetRecommendation()
    {
        try {

            // 1. Generate the unique cache key
            $cacheKey = $this->getCacheKey();

            // Since this is a monthly budget, caching for the whole month is appropriate.until the next month
            $cacheDuration = now()->endOfMonth()->diffInMinutes(now());
            // 2. Check if the response is already cached
            $responseText = Cache::remember($cacheKey, $cacheDuration, function () {

                $this->historicalData = $this->getHestoricalSpendingData();
                // dd($this->historicalData);

                if (empty($this->historicalData)) {
                    Log::error('no historical Data for this budget , Time : ' . now());
                    return null;
                }

                $prompt = $this->generatePrompt();

                try {
                    // gemini-2.5-flash-lite  gemini-2.5-flash
                    $response = Gemini::generativeModel(model: 'gemini-2.5-flash-lite')->generateContent($prompt);
                    $this->is_AI_generated_content_successfully = true;
                    return $response->text();

                } catch (\Exception $th) {
                    Log::error('Gemini AI Error, you have offline response: ' . $th->getMessage());
                    return $this->defaultRecommendation();
                }

            });
            if ($this->is_AI_generated_content_successfully)
                return $this->parseAIResponse($responseText);
            else
                return $responseText;

        } catch (\Exception $e) {
            Log::error('Budget AI Recommendation Error:' . $e->getMessage());
            return null;
        }
        // dd($historicalData);
    }
    private function getCacheKey(): string
    {
        // Concatenate the unique parameters
        return "budget.recommendation.user_{$this->userId}.category_{$this->categoryId}.{$this->year}_{$this->month}";
    }

    private function getHestoricalSpendingData()
    {
        $monthlyExpensesData = [];
        // Fetch historical spending data from the database
        // get the last 3 months of data (exclude the target month)

        $lastMonthEndDate = Carbon::create($this->year, $this->month, 1)->subMonth()->lastOfMonth();

        $historicalExpenses = Expense::query()
            ->select(
                DB::raw("SUM(amount) as total"),
                DB::raw("COUNT(id) as count"),
                DB::raw("MONTH(date) as month"),
                DB::raw("YEAR(date) as year"),
                DB::raw("GROUP_CONCAT(title SEPARATOR '||' ) AS titles")
            )
            ->forUser($this->userId)
            ->where('category_id', $this->categoryId)
            ->inDateRange($lastMonthEndDate->copy()->subMonths(2)->startOfMonth(), $lastMonthEndDate)
            ->groupBy('month', 'year')
            ->latest('month')
            ->get();


        foreach ($historicalExpenses as $data) {
            $monthlyExpensesData[] = [
                'month' => $data->month,
                'year' => $data->year,
                'total' => $data->total,
                'count' => $data->count,
                'titles' => explode('||', $data->titles),
            ];
        }

        $monthlyTotals = array_column($monthlyExpensesData, 'total');
        // dd(array_sum($monthlyTotals));
        // dd($monthlyTotals);

        return [
            'data' => $monthlyExpensesData,
            'max' => !empty($monthlyTotals) ? max($monthlyTotals) : 0,
            'min' => !empty($monthlyTotals) ? min($monthlyTotals) : 0,
            'average' => !empty($monthlyTotals) ? array_sum($monthlyTotals) / count($monthlyTotals) : 0,
            'trend' => $this->calculateTrend($monthlyTotals),
        ];
    }

    private function calculateTrend(?array $monthlyTotals)
    {
        $totalsCount = count($monthlyTotals);

        if ($totalsCount < 2) {
            return 'stable';
        }
        // compare most recent month with the oldest month
        $newest = $monthlyTotals[0];
        $oldest = $monthlyTotals[$totalsCount - 1];

        $percentageChange = ($oldest == 0) ? 0 : (($newest - $oldest) / $oldest) * 100;

        if ($percentageChange > 10) {
            return 'increasing';
        } elseif ($percentageChange < -10) {
            return 'decreasing';
        }
        return 'stable';
    }

    // Create the prompt for Gemini-ai api
    private function generatePrompt()
    {
        $categoryName = $this->categoryId ? Category::find($this->categoryId)?->name ?? 'this category' : 'overall spending';

        $targetMonth = Carbon::create($this->year, $this->month, 1)->format('F Y');

        $prompt = "You are a personal finance advisor. Analyze the following spending data and provide a budget recommendation.\n\n";

        $prompt .= "Category: {$categoryName}\n";
        $prompt .= "Target Month: {$targetMonth}\n";
        $prompt .= "Historical Spending (Last 3 Months): \n";
        foreach ($this->historicalData['data'] as $expense) {
            $prompt .= "{$expense['month']}/{$expense['year']}: \${$expense['total']} , ({$expense['count']} expenses)";
            if (!empty($expense['titles'])) {
                $prompt .= " Top items: " . implode(', ', array_slice($expense['titles'], 0, 5));
            }
            $prompt .= "\n";
        }

        $prompt .= "\nSpending Statistics:\n";
        $prompt .= "- Average: \$" . number_format($this->historicalData['average'], 2) . "\n";
        $prompt .= "- Minimum: \$" . number_format($this->historicalData['min'], 2) . "\n";
        $prompt .= "- Maximum: \$" . number_format($this->historicalData['max'], 2) . "\n";
        $prompt .= "- Trend: {$this->historicalData['trend']}\n\n";

        $prompt .= "Based on this data, provide:\n";
        $prompt .= "1. A recommended budget amount (single number)\n";
        $prompt .= "2. A minimum safe amount\n";
        $prompt .= "3. A maximum comfortable amount\n";
        $prompt .= "4. A brief explanation (2-3 sentences) why you recommend this amount\n";
        $prompt .= "5. One actionable tip to stay within budget\n\n";

        $prompt .= "Format your response as JSON with these exact keys:\n";
        $prompt .= '{"recommended": 500, "min": 450, "max": 550, "explanation": "...", "tip": "..."}';

        return $prompt;
    }

    private function parseAiResponse($responseText): array
    {
        try {
            if (preg_match("/\{[^}]+\}/", $responseText, $match))
                $json = json_decode($match[0], true);

            if ($json && isset($json['recommended'])) {
                Log::alert('Ai Response successfully');
                return [
                    'recommended' => (float) $json['recommended'] ?? $json['recommended'],
                    'max' => (float) $json['max'] ?? $json['recommended'] * 1.1,
                    'min' => (float) $json['min'] ?? $json['recommended'] * 0.9,
                    'explanation' => $json['explanation'] ?? 'Based on your spending patterns.',
                    'tip' => $json['tip'] ?? 'Track your expenses regularly to stay on budget.',
                    'confidence' => $this->calculateConfidence(),
                ];
            }

            return $this->defaultRecommendation();
        } catch (\Exception $e) {
            Log::error('Failed to Parse AI response' . $e->getMessage());
            return $this->defaultRecommendation();
        }
    }

    private function defaultRecommendation(): array
    {
        $average = $this->historicalData['average'];
        // Add 10% buffer for safety
        $recommended = round($average * 1.1, 2);
        return [
            'recommended' => round($recommended),
            'max' => (float) ceil($recommended * 1.1),
            'min' => (float) round($recommended * 0.9),
            'explanation' => "Based on your average spending of $" . number_format($average, 2) . " over the last 3 months, with a 10% buffer for unexpected expenses.",
            'tip' => "Review your expenses weekly to catch any overspending early.",
            'confidence' => $this->calculateConfidence(),
        ];
    }

    private function calculateConfidence()
    {
        $monthsWithData = count($this->historicalData['data']);

        if ($monthsWithData >= 3) {
            return 'high';
        } elseif ($monthsWithData === 2) {
            return 'medium';
        } else {
            return 'low';
        }
    }

    // check if the user has enough historical data
    public function hasEnoughHistoricalData()
    {
        $threeMonthsAgo = now()->subMonths(3)->startOfDay();

        $query = Expense::where('user_id', $this->userId)
            ->where('date', '>=', $threeMonthsAgo)
            ->where('category_id', $this->categoryId);

        // if($this->categoryId != null)
        //     dd($this->categoryId );
        return $query->count() >= 3;
    }
}
