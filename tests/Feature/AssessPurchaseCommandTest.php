<?php

namespace Tests\Feature;

use App\Ai\Agents\FinanceAssistant;
use Tests\TestCase;

class AssessPurchaseCommandTest extends TestCase
{
    public function test_the_assessment_is_a_guess_not_grounded_in_the_users_real_account_data(): void
    {
        FinanceAssistant::fake([
            "Probably, as long as it's not a recurring cost: a $600 purchase is "
            .'usually manageable under a typical monthly budget. I have no visibility '
            .'into your actual balance or spending, though.',
        ]);

        $this->artisan('assistant:assess-purchase', [
            'amount' => '600',
            'description' => 'a new laptop',
        ])
            ->expectsOutputToContain('no visibility into your actual balance')
            ->assertExitCode(0);

        FinanceAssistant::assertPrompted(
            fn ($prompt) => $prompt->prompt === 'Can I afford to spend 600.00 dollars on a new laptop? Answer with a short yes or no and a brief reason.'
        );
    }

    public function test_a_non_numeric_amount_is_rejected_before_any_call_is_made(): void
    {
        FinanceAssistant::fake();

        $this->artisan('assistant:assess-purchase', [
            'amount' => 'abc',
            'description' => 'a new laptop',
        ])
            ->expectsOutputToContain('Amount must be a positive number, got "abc"')
            ->assertExitCode(2);

        FinanceAssistant::assertNeverPrompted();
    }

    public function test_a_non_positive_amount_is_rejected_before_any_call_is_made(): void
    {
        FinanceAssistant::fake();

        $this->artisan('assistant:assess-purchase', [
            'amount' => '0',
            'description' => 'a new laptop',
        ])
            ->expectsOutputToContain('Amount must be a positive number, got "0"')
            ->assertExitCode(2);

        FinanceAssistant::assertNeverPrompted();
    }
}
