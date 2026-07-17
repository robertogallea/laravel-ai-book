<?php

namespace App\Console\Commands;

use App\Models\Transaction;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Laravel\Ai\Embeddings;

#[Signature('assistant:index-transactions')]
#[Description('Compute and store embeddings for any transaction not indexed yet')]
class IndexTransactionsCommand extends Command
{
    /**
     * Execute the console command.
     *
     * A transaction can be recorded without being searchable by meaning:
     * indexing is the separate step that makes it so, computing an
     * embedding for its description and storing it alongside the row.
     * Already-indexed transactions are left untouched, so running this
     * command again after new transactions arrive only pays for what is
     * actually new.
     */
    public function handle(): int
    {
        $pending = Transaction::query()->whereNull('embedding')->get();

        if ($pending->isEmpty()) {
            $this->components->info('Every transaction is already indexed.');

            return Command::SUCCESS;
        }

        $embeddings = Embeddings::for($pending->map->description()->all())->generate();

        foreach ($pending as $index => $transaction) {
            $transaction->update(['embedding' => $embeddings->embeddings[$index]]);
        }

        $this->components->info(sprintf('Indexed %d transaction(s).', $pending->count()));

        return Command::SUCCESS;
    }
}
