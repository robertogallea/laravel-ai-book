<?php

namespace App\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Http;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

/**
 * Written once, on the server side of the connection, for every client
 * that ever connects to reuse: this application is only one of
 * potentially many consumers, so nothing here is specific to how the
 * finance assistant happens to use it, unlike App\Ai\Tools\GetExchangeRateTool,
 * the ad-hoc integration this MCP server replaces.
 */
#[Description('Get the exchange rate from one currency to another, both given as three-letter codes (e.g. USD, EUR).')]
class GetExchangeRateTool extends Tool
{
    public function handle(Request $request): Response
    {
        $from = strtoupper((string) $request->get('from'));
        $to = strtoupper((string) $request->get('to'));

        $result = Http::get("https://open.er-api.com/v6/latest/{$from}");

        if ($result->failed() || $result->json('result') !== 'success') {
            return Response::error("Could not retrieve exchange rates for \"{$from}\" right now.");
        }

        $rate = $result->json("rates.{$to}");

        if ($rate === null) {
            return Response::error("Unknown currency code \"{$to}\".");
        }

        return Response::text(sprintf('1 %s = %s %s', $from, $rate, $to));
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'from' => $schema->string()->description('Three-letter source currency code.')->required(),
            'to' => $schema->string()->description('Three-letter target currency code.')->required(),
        ];
    }
}
