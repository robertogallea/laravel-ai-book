<?php

namespace Tests\Feature;

use App\Ai\Agents\ExpenseExtractor;
use App\Ai\Agents\PurchaseAdvisor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Ai;
use Tests\TestCase;

class ModelRoutingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    public function test_categorization_is_routed_to_the_cheapest_available_model(): void
    {
        ExpenseExtractor::fake([
            ['amount' => 12.50, 'category' => 'groceries', 'date' => '2026-07-19'],
        ]);

        $this->artisan('assistant:log-expense', [
            'description' => 'Coffee, 12.50 dollars, today.',
        ])->assertExitCode(0);

        // Asserted against whatever the configured provider actually
        // resolves as its cheapest model, not a literal string: the
        // routing is provider-relative by design (see #[UseCheapestModel]),
        // and a test tied to one provider's concrete model name would break
        // the moment a different provider is configured, exactly as
        // PROVIDER-SWITCH-EXPERIMENT.md documents.
        $cheapest = Ai::textProviderFor(new ExpenseExtractor, null)->cheapestTextModel();

        ExpenseExtractor::assertPrompted(fn ($prompt) => $prompt->model === $cheapest);
    }

    public function test_planning_is_routed_to_the_smartest_available_model(): void
    {
        PurchaseAdvisor::fake([
            ['affordable' => true, 'reasoning' => 'Well within budget.', 'suggested_action' => null],
        ]);

        $this->artisan('assistant:assess-purchase', [
            'amount' => '600',
            'description' => 'a new laptop',
            '--user' => $this->user->email,
        ])->assertExitCode(0);

        $smartest = Ai::textProviderFor(new PurchaseAdvisor($this->user), null)->smartestTextModel();

        PurchaseAdvisor::assertPrompted(fn ($prompt) => $prompt->model === $smartest);
    }

    public function test_categorization_and_planning_no_longer_resolve_to_the_same_model(): void
    {
        ExpenseExtractor::fake([
            ['amount' => 12.50, 'category' => 'groceries', 'date' => '2026-07-19'],
        ]);

        PurchaseAdvisor::fake([
            ['affordable' => true, 'reasoning' => 'Well within budget.', 'suggested_action' => null],
        ]);

        $this->artisan('assistant:log-expense', [
            'description' => 'Coffee, 12.50 dollars, today.',
        ])->assertExitCode(0);

        $this->artisan('assistant:assess-purchase', [
            'amount' => '600',
            'description' => 'a new laptop',
            '--user' => $this->user->email,
        ])->assertExitCode(0);

        // Neither call site chose a model itself: the routing lives once,
        // on each agent class, and every caller inherits it for free.
        // Asserted against the resolved default for the configured
        // provider, not a literal string, for the same reason as above.
        $default = Ai::textProviderFor(new ExpenseExtractor, null)->defaultTextModel();

        ExpenseExtractor::assertPrompted(fn ($prompt) => $prompt->model !== $default);
        PurchaseAdvisor::assertPrompted(fn ($prompt) => $prompt->model !== $default);
    }
}
