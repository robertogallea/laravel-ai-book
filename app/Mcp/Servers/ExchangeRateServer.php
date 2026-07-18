<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\GetExchangeRateTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;

/**
 * Stands in, for this book's example, for a server run and maintained by
 * someone else entirely: in a real deployment this would be a separate
 * service, reached with Client::web(...) instead of Client::local(...),
 * exposing whatever currency and market-data tools its own team decides
 * to publish. Running it locally here keeps the example reproducible
 * without depending on any specific external company's uptime.
 */
#[Name('Exchange Rate Server')]
#[Instructions('Provides exchange rates between currency pairs.')]
class ExchangeRateServer extends Server
{
    /**
     * @var array<int, class-string>
     */
    protected array $tools = [
        GetExchangeRateTool::class,
    ];
}
