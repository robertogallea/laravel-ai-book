<?php

namespace App\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Written by hand, for this application alone, against one specific
 * external provider's response shape. Nothing here is reusable: another
 * application that needs the same exchange rate has to write this exact
 * same integration again, from scratch, against whatever provider it
 * happens to pick.
 */
class GetExchangeRateTool implements Tool
{
    public function description(): Stringable|string
    {
        return 'Get the exchange rate from one currency to another, both given as three-letter codes (e.g. USD, EUR).';
    }

    public function handle(Request $request): Stringable|string
    {
        $from = strtoupper($request['from']);
        $to = strtoupper($request['to']);

        // No timeout, no retry, no check that the call even succeeded: a
        // slow or unreachable provider fails this call with whatever
        // uncaught exception the HTTP client happens to throw.
        $rates = Http::get("https://open.er-api.com/v6/latest/{$from}")->json('rates');

        // No validation that the target currency is actually present in
        // the response: an unrecognized code silently turns into "null"
        // in the sentence below instead of a clear error.
        return sprintf('1 %s = %s %s', $from, $rates[$to], $to);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'from' => $schema->string()->required(),
            'to' => $schema->string()->required(),
        ];
    }
}
