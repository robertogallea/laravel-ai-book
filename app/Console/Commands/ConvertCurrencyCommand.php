<?php

namespace App\Console\Commands;

use App\Ai\Agents\CurrencyAdvisor;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('assistant:convert-currency {question : The currency-conversion question to ask the assistant}')]
#[Description('Ask the assistant a currency-conversion question, backed by a live exchange rate')]
class ConvertCurrencyCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $response = (new CurrencyAdvisor)->prompt($this->argument('question'));

        $this->line($response->text);
    }
}
