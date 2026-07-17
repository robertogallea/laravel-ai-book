<?php

namespace App\Console\Commands;

use App\Ai\Agents\FinanceAssistant;
use App\Models\Transaction;
use App\Support\VectorStore;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Laravel\Ai\Embeddings;

#[Signature('assistant:ask-spending {question : A question about the user\'s spending history}')]
#[Description('Ask the assistant a question about the user\'s spending history, grounded in the user\'s actual transactions')]
class AskSpendingCommand extends Command
{
    /**
     * How many of the most relevant transactions to retrieve and hand to
     * the assistant alongside the question. Bounded on purpose: handing
     * over every transaction ever recorded would defeat the point of
     * retrieval and inflate every request with irrelevant context.
     */
    private const TRANSACTIONS_RETRIEVED = 5;

    /**
     * Execute the console command.
     *
     * The question is answered grounded in the user's own transaction
     * history: the transactions most relevant to it are retrieved by
     * semantic similarity and handed to the assistant alongside the
     * question, instead of leaving it to answer from general knowledge
     * alone.
     */
    public function handle(): void
    {
        $question = $this->argument('question');

        $response = (new FinanceAssistant)->prompt($this->augmentedPrompt($question));

        $this->line($response->text);
    }

    /**
     * Fold the transactions most relevant to the question into the prompt
     * sent to the assistant. A question is sent unchanged if nothing has
     * been indexed yet, since there is nothing real to ground it in.
     */
    private function augmentedPrompt(string $question): string
    {
        $indexed = Transaction::query()->whereNotNull('embedding')->get();

        if ($indexed->isEmpty()) {
            return $question;
        }

        $questionEmbedding = Embeddings::for([$question])->generate()->first();

        $relevant = VectorStore::nearest($questionEmbedding, $indexed, self::TRANSACTIONS_RETRIEVED);

        $transactions = (new Collection($relevant))
            ->map(fn (Transaction $transaction) => '- '.$transaction->description())
            ->implode("\n");

        return <<<PROMPT
        Relevant transactions from the user's history:
        {$transactions}

        Question: {$question}
        PROMPT;
    }
}
