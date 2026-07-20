<?php

namespace App\Console\Commands;

use App\Ai\Agents\MonthlyReportSummarizer;
use App\Ai\Agents\OverspendingAdvisor;
use App\Console\Commands\Concerns\ReadsStructuredResponse;
use App\Console\Commands\Concerns\ResolvesUserOption;
use App\Models\Transaction;
use App\Support\CallTrace;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

#[Signature('assistant:generate-monthly-report {month? : Target month in Y-m format (e.g. 2026-07), defaults to the current month} {--user= : Email address of the user this report is for}')]
#[Description('Generate a spending report for the given month, or the current one')]
class GenerateMonthlyReportCommand extends Command
{
    use ReadsStructuredResponse;
    use ResolvesUserOption;

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
        $user = $this->resolveUserOption();

        if ($user === false) {
            return Command::INVALID;
        }

        // The month argument exists for ProcessReportQueueCommand, which
        // must report on whatever month a queued request was actually
        // made for, not whatever month happens to be current by the time
        // the batch runs: invoked directly with no argument, this still
        // behaves exactly as before, reporting on the current month.
        $month = $this->argument('month')
            ? Carbon::createFromFormat('Y-m', $this->argument('month'))
            : now();

        // Step 1: raccolta dati. Neither query depends on the other; an
        // application with an async runtime could dispatch them together,
        // here they simply run one after the other, a single console
        // command has no need for that complexity to make the same point
        // about coordination. Restricted to this user's own transactions,
        // the same boundary AskSpendingCommand already enforces on its
        // own retrieval: a report is never a mix of two users' spending.
        $transactions = Transaction::query()
            ->ownedBy($user)
            ->whereYear('occurred_at', $month->year)
            ->whereMonth('occurred_at', $month->month)
            ->get();

        $budgets = collect(config('finance.budgets'));

        // Step 2: categorizzazione. Deterministic aggregation, not a
        // single call to the model: every category configured in
        // config('finance.budgets') appears below, whether or not any
        // transaction happened to fall into it this month. Grouped once,
        // not re-scanned once per category.
        $spentByCategory = $transactions->groupBy('category')->map->sum('amount');

        $categories = $budgets->map(function (float $limit, string $category) use ($spentByCategory) {
            $spent = (float) ($spentByCategory[$category] ?? 0.0);

            return [
                'category' => $category,
                'spent' => $spent,
                'limit' => $limit,
                // A zero-budget category is never "a fraction over" a limit
                // of zero: any spending against it is unconditionally over
                // budget, not silently exempt from the check below.
                'over_budget_by' => $limit > 0.0 ? ($spent - $limit) / $limit : ($spent > 0.0 ? INF : 0.0),
            ];
        })->values();

        // Step 3: generazione del riepilogo. Esegue sempre, un'unica
        // chiamata al modello sui totali già calcolati sopra.
        $summaryPrompt = $categories
            ->map(fn (array $row) => sprintf('%s: %.2f of %.2f dollars spent', $row['category'], $row['spent'], $row['limit']))
            ->implode("\n");

        $summaryResponse = (new MonthlyReportSummarizer)->prompt($summaryPrompt);

        // Traced the same way as every other model call in this
        // application since the chapter on resilience, now also
        // attributed to the user this report was generated for: this is
        // what makes the cost of running this pipeline, once or a
        // thousand times, something to measure instead of something to
        // guess, per user rather than blended across all of them.
        CallTrace::record($summaryPrompt, $summaryResponse, user: $user);

        $summary = $this->stringField($summaryResponse->structured, 'summary');

        if ($summary === null) {
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

        CallTrace::record($overBudgetPrompt, $recommendationsResponse, user: $user);

        $recommendations = $this->stringField($recommendationsResponse->structured, 'recommendations');

        if ($recommendations === null) {
            $this->components->error('The assistant returned incomplete recommendations. Please try again in a moment.');

            return Command::FAILURE;
        }

        $this->line($recommendations);

        return Command::SUCCESS;
    }
}
