<?php

namespace App\Console\Commands;

use App\Ai\Agents\FinanceAssistant;
use App\Models\Transaction;
use App\Support\CallTrace;
use App\Support\VectorStore;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Http\Client\HttpClientException;
use Illuminate\Support\Collection;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Exceptions\AiException;

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
     * The minimum cosine similarity a transaction must reach to count as
     * relevant to the question. The limit above only bounds how many
     * transactions come back, not whether any of them relate to the
     * question at all: without this floor, an unrelated question would
     * still receive whatever ranks highest, presented as if it were
     * grounded in real data.
     */
    private const MINIMUM_RELEVANCE = 0.3;

    /**
     * Execute the console command.
     *
     * The question is answered grounded in the user's own transaction
     * history: the transactions most relevant to it are retrieved by
     * semantic similarity and handed to the assistant alongside the
     * question, instead of leaving it to answer from general knowledge
     * alone.
     */
    public function handle(): int
    {
        $question = $this->argument('question');

        try {
            $prompt = $this->augmentedPrompt($question);
        } catch (AiException|HttpClientException) {
            $this->components->error('Could not reach the embeddings provider right now. Please try again in a moment.');

            return Command::FAILURE;
        }

        $response = (new FinanceAssistant)->prompt($prompt);

        // Traced the same way as every other model call in this
        // application since the chapter on resilience: this is what makes
        // the cost of answering the same question, once or repeatedly,
        // something to measure instead of something to guess.
        CallTrace::record($prompt, $response);

        $this->line($response->text);

        return Command::SUCCESS;
    }

    /**
     * Fold the transactions most relevant to the question into the prompt
     * sent to the assistant. A question is sent unchanged if nothing has
     * been indexed yet, or if nothing indexed clears the relevance floor:
     * in both cases there is nothing real to ground the answer in.
     */
    private function augmentedPrompt(string $question): string
    {
        $indexed = Transaction::query()->whereNotNull('embedding')->get();

        if ($indexed->isEmpty()) {
            return $question;
        }

        $questionEmbedding = Embeddings::for([$question])->generate()->first();

        $relevant = VectorStore::nearest($questionEmbedding, $indexed, self::TRANSACTIONS_RETRIEVED, self::MINIMUM_RELEVANCE);

        if (empty($relevant)) {
            return $question;
        }

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
