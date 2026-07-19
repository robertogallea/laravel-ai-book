<?php

namespace App\Console\Commands;

use App\Ai\Agents\MonthlyReportSummarizer;
use App\Ai\Agents\OverspendingAdvisor;
use App\Models\Transaction;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('assistant:generate-monthly-report')]
#[Description("Generate this month's spending report")]
class GenerateMonthlyReportCommand extends Command
{
    /**
     * How far over its budget a category has to be, as a fraction of the
     * budget itself, before the recommendations step below runs at all.
     * Fixed here, by the pipeline, not left for a model to judge.
     */
    private const OVERBUDGET_THRESHOLD = 0.10;

    /**
     * Execute the console command.
     *
     * Every step below runs in a fixed order decided here, not by a model
     * deciding what to check and when. Data collection and categorization
     * are plain application code: not a single model call is spent on
     * them, and every category config('finance.budgets') tracks appears
     * in the result, whether or not it had any transactions this month.
     * The model is only ever invoked for the two steps that genuinely
     * need it: a summary, always, and recommendations, only when the
     * over-budget check below actually finds something.
     */
    public function handle(): int
    {
        // Step 1: raccolta dati. Neither query depends on the other; an
        // application with an async runtime could dispatch them together,
        // here they simply run one after the other, a single console
        // command has no need for that complexity to make the same point
        // about coordination.
        $transactions = Transaction::whereYear('occurred_at', now()->year)
            ->whereMonth('occurred_at', now()->month)
            ->get();

        $budgets = collect(config('finance.budgets'));

        // Step 2: categorizzazione. Deterministic aggregation, not a
        // single call to the model: every category configured in
        // config('finance.budgets') appears below, whether or not any
        // transaction happened to fall into it this month.
        $categories = $budgets->map(function (float $limit, string $category) use ($transactions) {
            $spent = (float) $transactions->where('category', $category)->sum('amount');

            return [
                'category' => $category,
                'spent' => $spent,
                'limit' => $limit,
                'over_budget_by' => $limit > 0.0 ? ($spent - $limit) / $limit : 0.0,
            ];
        })->values();

        // Step 3: generazione del riepilogo. Esegue sempre, un'unica
        // chiamata al modello sui totali già calcolati sopra.
        $summaryPrompt = $categories
            ->map(fn (array $row) => sprintf('%s: %.2f of %.2f dollars spent', $row['category'], $row['spent'], $row['limit']))
            ->implode("\n");

        $summaryResponse = (new MonthlyReportSummarizer)->prompt($summaryPrompt);
        $summary = $summaryResponse->structured['summary'] ?? null;

        if (! is_string($summary) || $summary === '') {
            $this->components->error('The assistant returned an incomplete summary. Please try again in a moment.');

            return Command::FAILURE;
        }

        $this->line($summary);

        // Step 4: branching condizionale. Solo le categorie che superano
        // la soglia decisa qui innescano la chiamata aggiuntiva: la
        // decisione se fare o meno questo passo appartiene alla pipeline,
        // mai al modello.
        $overBudget = $categories->filter(fn (array $row) => $row['over_budget_by'] > self::OVERBUDGET_THRESHOLD);

        if ($overBudget->isEmpty()) {
            return Command::SUCCESS;
        }

        $overBudgetPrompt = $overBudget
            ->map(fn (array $row) => sprintf('%s: %.0f%% over budget', $row['category'], $row['over_budget_by'] * 100))
            ->implode("\n");

        $recommendationsResponse = (new OverspendingAdvisor)->prompt($overBudgetPrompt);
        $recommendations = $recommendationsResponse->structured['recommendations'] ?? null;

        if (is_string($recommendations) && $recommendations !== '') {
            $this->line($recommendations);
        }

        return Command::SUCCESS;
    }
}
