<?php

namespace App\Console\Commands;

use App\Models\Transaction;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Http\Client\HttpClientException;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Exceptions\AiException;
use RuntimeException;

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

        try {
            $embeddings = Embeddings::for($pending->map->description()->all())->generate();
        } catch (AiException|HttpClientException) {
            $this->components->error('Could not reach the embeddings provider right now. Please try again in a moment.');

            return Command::FAILURE;
        }

        // The provider is expected to return exactly one embedding per
        // input, in the same order: that is what lets the loop below match
        // each embedding back to the transaction it was computed from by
        // position alone. If that ever stops holding, failing loudly here
        // is safer than silently writing one transaction's embedding onto
        // another's row.
        if (count($embeddings->embeddings) !== $pending->count()) {
            throw new RuntimeException(sprintf(
                'Expected %d embedding(s) from the provider, got %d: refusing to guess which transaction each one belongs to.',
                $pending->count(),
                count($embeddings->embeddings),
            ));
        }

        foreach ($pending as $index => $transaction) {
            $transaction->update(['embedding' => $embeddings->embeddings[$index]]);
        }

        $this->components->info(sprintf('Indexed %d transaction(s).', $pending->count()));

        return Command::SUCCESS;
    }
}
