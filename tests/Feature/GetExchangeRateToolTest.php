<?php

namespace Tests\Feature;

use App\Ai\Tools\GetExchangeRateTool;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

class GetExchangeRateToolTest extends TestCase
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

        $result = (string) (new GetExchangeRateTool)->handle(new Request(['from' => 'usd', 'to' => 'eur']));

        $this->assertSame('1 USD = 0.92 EUR', $result);
    }

    public function test_an_unknown_target_currency_crashes_the_call_instead_of_a_clear_error(): void
    {
        // This is the fragility this version of the tool is built to
        // illustrate: nothing here checks that the requested currency is
        // actually present in the provider's response, so an unrecognized
        // code reaches an undefined array offset instead of a message the
        // model, or the user behind it, could actually act on.
        Http::fake([
            'https://open.er-api.com/v6/latest/USD' => Http::response([
                'result' => 'success',
                'base_code' => 'USD',
                'rates' => ['EUR' => 0.92],
            ]),
        ]);

        $this->expectExceptionMessage('Undefined array key "XXX"');

        (new GetExchangeRateTool)->handle(new Request(['from' => 'usd', 'to' => 'xxx']));
    }
}
