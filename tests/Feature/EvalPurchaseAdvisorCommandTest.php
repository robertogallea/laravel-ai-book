<?php

namespace Tests\Feature;

use App\Ai\Agents\PurchaseAdvisor;
use Tests\TestCase;

class EvalPurchaseAdvisorCommandTest extends TestCase
{
    public function test_the_eval_set_passes_against_a_correctly_behaving_prompt(): void
    {
        PurchaseAdvisor::fake([
            [
                'affordable' => true,
                'reasoning' => 'The current balance comfortably covers this purchase with budget to spare.',
                'suggested_action' => null,
            ],
            [
                'affordable' => false,
                'reasoning' => 'The purchase would exceed the electronics budget unless the unused '
                    .'"Streaming Plus" subscription is cancelled first.',
                'suggested_action' => [
                    'subscription_name' => 'Streaming Plus',
                    'monthly_cost' => 12.99,
                    'days_unused' => 97,
                ],
            ],
        ]);

        $this->artisan('assistant:eval-purchase-advisor')
            ->expectsOutputToContain('Purchase-advisor eval: 2/2 cases passed.')
            ->assertExitCode(0);
    }

    public function test_a_response_with_no_real_reasoning_fails_the_qualitative_criterion(): void
    {
        // "affordable" alone is not enough to call this response correct:
        // an empty explanation is exactly the kind of gap a single-field
        // check would miss and a judge model would catch.
        PurchaseAdvisor::fake([
            ['affordable' => true, 'reasoning' => '', 'suggested_action' => null],
            [
                'affordable' => false,
                'reasoning' => 'The purchase would exceed the electronics budget unless the unused '
                    .'"Streaming Plus" subscription is cancelled first.',
                'suggested_action' => [
                    'subscription_name' => 'Streaming Plus',
                    'monthly_cost' => 12.99,
                    'days_unused' => 97,
                ],
            ],
        ]);

        $this->artisan('assistant:eval-purchase-advisor')
            ->expectsOutputToContain('Purchase-advisor eval: 1/2 cases passed.')
            ->expectsOutputToContain('Failed cases: comfortably_affordable_purchase')
            ->assertExitCode(1);
    }
}
