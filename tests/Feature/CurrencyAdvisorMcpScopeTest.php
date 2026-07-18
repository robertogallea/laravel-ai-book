<?php

namespace Tests\Feature;

use App\Ai\Agents\CurrencyAdvisor;
use Tests\TestCase;

class CurrencyAdvisorMcpScopeTest extends TestCase
{
    public function test_only_the_granted_exchange_rate_tool_is_exposed_to_the_agent(): void
    {
        $tools = (new CurrencyAdvisor)->tools();

        $names = collect($tools)->map(fn ($tool) => $tool->name)->values()->all();

        $this->assertSame(['get-exchange-rate-tool'], $names);
    }
}
