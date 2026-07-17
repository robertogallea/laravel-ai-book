<?php

namespace Tests\Feature;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\Support\ProbeApprovalCommand;
use Tests\TestCase;

class RequiresApprovalTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app[Kernel::class]->registerCommand(new ProbeApprovalCommand);
    }

    public function test_an_approved_action_that_fails_during_execution_is_recorded_and_reported_instead_of_crashing(): void
    {
        Log::spy();

        $this->artisan('test:probe-approval')
            ->expectsConfirmation('Approve this action?', 'yes')
            ->expectsOutputToContain('Action approved but execution failed: simulated execution failure')
            ->assertExitCode(1);

        Log::shouldHaveReceived('info')
            ->once()
            ->with('proposed_action.failed', Mockery::on(
                fn (array $payload) => $payload['type'] === 'probe_action'
                    && $payload['detail'] === 'simulated execution failure',
            ));
    }

    public function test_a_proposal_is_not_silently_approved_when_no_interactive_terminal_is_available(): void
    {
        Log::spy();

        $this->artisan('test:probe-approval', ['--no-interaction' => true])
            ->expectsOutputToContain('This action requires an explicit approval, but no interactive terminal is available')
            ->assertExitCode(1);

        Log::shouldHaveReceived('info')
            ->once()
            ->with('proposed_action.skipped', Mockery::on(
                fn (array $payload) => $payload['type'] === 'probe_action',
            ));

        Log::shouldNotHaveReceived('info', ['proposed_action.rejected', Mockery::any()]);
    }
}
