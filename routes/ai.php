<?php

use App\Mcp\Servers\ExchangeRateServer;
use Laravel\Mcp\Facades\Mcp;

// Run with: php artisan mcp:start exchange-rates
Mcp::local('exchange-rates', ExchangeRateServer::class);
