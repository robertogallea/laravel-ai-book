<?php

namespace Tests\Feature;

use App\Mcp\Servers\ExchangeRateServer;
use App\Mcp\Tools\GetExchangeRateTool;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ExchangeRateServerTest extends TestCase
{
    public function test_it_reports_the_rate_between_two_currencies(): void
    {
        Http::fake([
            'https://open.er-api.com/v6/latest/USD' => Http::response([
                'result' => 'success',
                'base_code' => 'USD',
                'rates' => ['EUR' => 0.92, 'GBP' => 0.79],
            ]),
        ]);

        ExchangeRateServer::tool(GetExchangeRateTool::class, ['from' => 'usd', 'to' => 'eur'])
            ->assertOk()
            ->assertSee('1 USD = 0.92 EUR');
    }

    public function test_an_unknown_target_currency_is_reported_as_an_explicit_error(): void
    {
        Http::fake([
            'https://open.er-api.com/v6/latest/USD' => Http::response([
                'result' => 'success',
                'base_code' => 'USD',
                'rates' => ['EUR' => 0.92],
            ]),
        ]);

        ExchangeRateServer::tool(GetExchangeRateTool::class, ['from' => 'usd', 'to' => 'xxx'])
            ->assertHasErrors()
            ->assertSee('Unknown currency code "XXX"');
    }

    public function test_a_failed_upstream_call_is_reported_as_an_explicit_error(): void
    {
        Http::fake([
            'https://open.er-api.com/v6/latest/USD' => Http::response(status: 503),
        ]);

        ExchangeRateServer::tool(GetExchangeRateTool::class, ['from' => 'usd', 'to' => 'eur'])
            ->assertHasErrors()
            ->assertSee('Could not retrieve exchange rates for "USD" right now.');
    }
}
