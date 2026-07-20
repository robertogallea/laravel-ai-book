<?php

namespace App\Console\Commands;

use App\Ai\Agents\FinanceAssistant;
use App\Models\Transaction;
use App\Models\User;
use App\Support\CallTrace;
use App\Support\VectorStore;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Http\Client\HttpClientException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Exceptions\AiException;

#[Signature('assistant:ask-spending {question : A question about the user\'s spending history} {--user= : Email address of the authenticated user asking this question}')]
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
     * How long a cached answer stays valid on its own, regardless of
     * whether Transaction::booted() has invalidated it sooner. A secondary
     * bound: the version bump handles the case that actually matters here
     * (new transactions changing the correct answer), this one only
     * guards against a cached answer surviving indefinitely if that
     * mechanism were ever bypassed.
     */
    private const CACHE_TTL_SECONDS = 3600;

    /**
     * Execute the console command.
     *
     * The question is answered grounded in the user's own transaction
     * history: the transactions most relevant to it are retrieved by
     * semantic similarity and handed to the assistant alongside the
     * question, instead of leaving it to answer from general knowledge
     * alone. A question asked before, in the same words, by the same user,
     * with nothing about that user's transactions having changed since, is
     * answered from cache instead: no embeddings call, no call to the
     * assistant, nothing traced, because nothing was actually asked of
     * either. Every step from here on, retrieval, cache key, and trace,
     * takes the resolved user as an explicit argument: none of them fall
     * back to answering for whichever user happens to be current if none
     * is given, because none of them accept being called without one.
     */
    public function handle(): int
    {
        $question = $this->argument('question');
        $email = $this->option('user');

        if ($email === null) {
            $this->components->error('The --user option is required: no question can be answered without knowing whose transactions to consult.');

            return Command::INVALID;
        }

        $user = User::where('email', $email)->first();

        if ($user === null) {
            $this->components->error("No user found with email \"{$email}\".");

            return Command::INVALID;
        }

        $cacheKey = $this->cacheKey($question, $user);

        $cached = Cache::get($cacheKey);

        if ($cached !== null) {
            $this->line($cached);

            return Command::SUCCESS;
        }

        try {
            $prompt = $this->augmentedPrompt($question, $user);
        } catch (AiException|HttpClientException) {
            $this->components->error('Could not reach the embeddings provider right now. Please try again in a moment.');

            return Command::FAILURE;
        }

        $response = (new FinanceAssistant)->prompt($prompt);

        // Traced the same way as every other model call in this
        // application since the chapter on resilience, now also
        // attributed to the user this specific answer was grounded in:
        // this is what makes it possible to reconstruct, after the fact,
        // not just what was asked but who asked it.
        CallTrace::record($prompt, $response, user: $user);

        Cache::put($cacheKey, $response->text, self::CACHE_TTL_SECONDS);

        $this->line($response->text);

        return Command::SUCCESS;
    }

    /**
     * A cache key scoped to the question itself, normalized so that trivial
     * differences in case or surrounding whitespace still hit the same
     * entry, to the current spending-answers cache version, and to the
     * user asking: two different users asking the same question in the
     * same words must never be answered from each other's cached entry.
     */
    private function cacheKey(string $question, User $user): string
    {
        $version = Cache::get(Transaction::SPENDING_ANSWERS_CACHE_VERSION_KEY, 0);

        return sprintf('spending_answer:%d:%d:%s', $version, $user->id, md5(strtolower(trim($question))));
    }

    /**
     * Fold the transactions most relevant to the question into the prompt
     * sent to the assistant, restricted from retrieval onward to those
     * owned by the given user: a transaction belonging to anyone else
     * never becomes a candidate in the first place, regardless of how
     * relevant its embedding would otherwise rank. A question is sent
     * unchanged if this user has nothing indexed yet, or if nothing
     * indexed clears the relevance floor: in both cases there is nothing
     * real to ground the answer in.
     */
    private function augmentedPrompt(string $question, User $user): string
    {
        $indexed = Transaction::query()->ownedBy($user)->whereNotNull('embedding')->get();

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
