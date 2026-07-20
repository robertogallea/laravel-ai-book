<?php

namespace Tests\Feature;

use App\Ai\Agents\MonthlyReportSummarizer;
use App\Models\ReportRequest;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Regression coverage for three bugs in ProcessReportQueueCommand:
 *
 * 1. A request carries no month, so a batch delayed past a month
 *    boundary would silently report on the wrong month. Fixed by
 *    recording the target month at request time (RequestMonthlyReportCommand)
 *    and passing it through to GenerateMonthlyReportCommand.
 * 2. A blanket "status = pending" update at the end of a run could sweep
 *    up a request that arrived after this run's work had already been
 *    decided, marking it "processed" without ever having been covered by
 *    that run; and nothing prevented two overlapping runs from both
 *    generating the same report. Fixed by snapshotting the pending
 *    requests up front, updating only that snapshot's IDs, and guarding
 *    the whole run with a cache lock.
 * 3. Batching grouped requests by month alone, so two different users
 *    requesting the same month would have been blended into one report.
 *    Fixed by grouping by (month, user) together (see Chapter 12).
 */
class ProcessReportQueueCorrectnessTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['email' => 'user@example.com']);
    }

    public function test_a_request_queued_for_a_past_month_is_reported_on_that_month_not_the_current_one(): void
    {
        Transaction::factory()->create([
            'user_id' => $this->user->id,
            'category' => 'groceries',
            'amount' => 50.00,
            'occurred_at' => '2026-06-15',
        ]);

        // This month's own transactions must not leak into a report for
        // a different, previously-requested month.
        Transaction::factory()->create([
            'user_id' => $this->user->id,
            'category' => 'groceries',
            'amount' => 999.00,
            'occurred_at' => now(),
        ]);

        config(['finance.budgets' => ['groceries' => 400.00]]);

        ReportRequest::create(['user_id' => $this->user->id, 'month' => '2026-06', 'status' => 'pending']);

        MonthlyReportSummarizer::fake(fn (string $prompt) => [
            'summary' => "Prompt was: {$prompt}",
        ]);

        $this->artisan('assistant:process-report-queue')
            ->expectsOutputToContain('Prompt was: groceries: 50.00 of 400.00 dollars spent')
            ->assertExitCode(0);

        $this->assertSame('processed', ReportRequest::sole()->status);
    }

    public function test_requests_for_two_different_months_are_processed_as_two_separate_reports(): void
    {
        config(['finance.budgets' => ['groceries' => 400.00]]);

        ReportRequest::create(['user_id' => $this->user->id, 'month' => '2026-05', 'status' => 'pending']);
        ReportRequest::create(['user_id' => $this->user->id, 'month' => '2026-06', 'status' => 'pending']);

        MonthlyReportSummarizer::fake([
            ['summary' => 'May report.'],
            ['summary' => 'June report.'],
        ]);

        $this->artisan('assistant:process-report-queue')
            ->expectsOutputToContain("Processed 1 pending report request(s) for 2026-05 for {$this->user->email} in a single batched run.")
            ->expectsOutputToContain("Processed 1 pending report request(s) for 2026-06 for {$this->user->email} in a single batched run.")
            ->assertExitCode(0);

        $this->assertSame(2, ReportRequest::where('status', 'processed')->count());
    }

    public function test_requests_for_the_same_month_from_two_different_users_are_processed_as_two_separate_reports(): void
    {
        config(['finance.budgets' => ['groceries' => 400.00]]);

        $otherUser = User::factory()->create(['email' => 'other@example.com']);

        Transaction::factory()->create(['user_id' => $this->user->id, 'category' => 'groceries', 'amount' => 50.00, 'occurred_at' => '2026-06-10']);
        Transaction::factory()->create(['user_id' => $otherUser->id, 'category' => 'groceries', 'amount' => 60.00, 'occurred_at' => '2026-06-10']);

        ReportRequest::create(['user_id' => $this->user->id, 'month' => '2026-06', 'status' => 'pending']);
        ReportRequest::create(['user_id' => $otherUser->id, 'month' => '2026-06', 'status' => 'pending']);

        MonthlyReportSummarizer::fake(fn (string $prompt) => ['summary' => "Prompt was: {$prompt}"]);

        $this->artisan('assistant:process-report-queue')
            ->expectsOutputToContain('Prompt was: groceries: 50.00 of 400.00 dollars spent')
            ->expectsOutputToContain('Prompt was: groceries: 60.00 of 400.00 dollars spent')
            ->assertExitCode(0);

        $this->assertSame(2, ReportRequest::where('status', 'processed')->count());
    }

    public function test_a_request_that_arrives_mid_run_is_not_swept_into_processed(): void
    {
        config(['finance.budgets' => ['groceries' => 400.00]]);

        ReportRequest::create(['user_id' => $this->user->id, 'month' => '2026-06', 'status' => 'pending']);

        // Simulates a new request being queued by another user action
        // while this run's report is being generated: it must not be
        // touched by a run that had already decided its scope before
        // this request existed.
        MonthlyReportSummarizer::fake(function () {
            ReportRequest::create(['user_id' => $this->user->id, 'month' => '2026-06', 'status' => 'pending']);

            return ['summary' => 'June report.'];
        });

        $this->artisan('assistant:process-report-queue')->assertExitCode(0);

        $this->assertSame(1, ReportRequest::where('status', 'processed')->count());
        $this->assertSame(1, ReportRequest::where('status', 'pending')->count());
    }

    public function test_an_overlapping_run_does_not_reprocess_the_same_requests(): void
    {
        config(['finance.budgets' => ['groceries' => 400.00]]);

        ReportRequest::create(['user_id' => $this->user->id, 'month' => '2026-06', 'status' => 'pending']);

        // Simulate another process already holding the batch lock.
        $lock = Cache::lock('process-report-queue', 300);
        $this->assertTrue($lock->get());

        MonthlyReportSummarizer::fake(['summary' => 'June report.']);

        $this->artisan('assistant:process-report-queue')
            ->expectsOutputToContain('Another process-report-queue run is already in progress.')
            ->assertExitCode(0);

        $this->assertSame('pending', ReportRequest::sole()->status);
        MonthlyReportSummarizer::assertNeverPrompted();
    }
}
