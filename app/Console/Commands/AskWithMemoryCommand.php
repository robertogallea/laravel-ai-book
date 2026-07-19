<?php

namespace App\Console\Commands;

use App\Ai\Agents\FactExtractor;
use App\Ai\Agents\FinanceAssistant;
use App\Models\MemoryFact;
use App\Support\VectorStore;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Http\Client\HttpClientException;
use Illuminate\Support\Collection;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Exceptions\AiException;

#[Signature('assistant:ask-with-memory {question : The question or statement to send to the assistant}')]
#[Description('Send a one-off message to the assistant, grounded in facts remembered from previous sessions')]
class AskWithMemoryCommand extends Command
{
    /**
     * How many of the most relevant remembered facts to retrieve and hand
     * to the assistant alongside the question. Same reasoning as
     * AskSpendingCommand::TRANSACTIONS_RETRIEVED, applied to a different
     * data source.
     */
    private const FACTS_RETRIEVED = 3;

    /**
     * The minimum cosine similarity a fact must reach to count as relevant
     * to the question. Same reasoning as AskSpendingCommand::MINIMUM_RELEVANCE.
     */
    private const MINIMUM_RELEVANCE = 0.3;

    /**
     * Execute the console command.
     *
     * Every invocation of this command is a session on its own, the same
     * one-shot boundary AskCommand already has: nothing survives from one
     * call to the next except what was explicitly persisted below. A
     * question is first answered grounded in whatever facts a previous
     * session already left behind, retrieved by the same embedding and
     * similarity mechanism already built for the user's transactions
     * (see App\Support\VectorStore), applied here to a distinct data
     * source. Only afterward does this session decide whether it, in
     * turn, has left a fact worth remembering for the next one.
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

        $this->line($response->text);

        $this->rememberAnyFact($question);

        return Command::SUCCESS;
    }

    /**
     * Fold the facts most relevant to the question into the prompt sent to
     * the assistant. A question is sent unchanged if nothing has been
     * remembered yet, or if nothing remembered clears the relevance floor:
     * in both cases there is nothing to ground the answer in beyond the
     * question itself.
     */
    private function augmentedPrompt(string $question): string
    {
        $remembered = MemoryFact::all();

        if ($remembered->isEmpty()) {
            return $question;
        }

        $questionEmbedding = Embeddings::for([$question])->generate()->first();

        $relevant = VectorStore::nearest($questionEmbedding, $remembered, self::FACTS_RETRIEVED, self::MINIMUM_RELEVANCE);

        if (empty($relevant)) {
            return $question;
        }

        $facts = (new Collection($relevant))
            ->map(fn (MemoryFact $fact) => '- '.$fact->content)
            ->implode("\n");

        return <<<PROMPT
        Facts remembered from previous sessions:
        {$facts}

        Question: {$question}
        PROMPT;
    }

    /**
     * Extract and persist any fact worth remembering from this session's
     * question, embedding it immediately so it is retrievable by meaning
     * from the moment it exists. A failure here is reported to the log
     * only, never to the user: the question above was already answered
     * successfully, and a session that fails to remember something is a
     * worse outcome than one that fails loudly after already delivering
     * an answer.
     */
    private function rememberAnyFact(string $question): void
    {
        try {
            $extracted = (new FactExtractor)->prompt($question);
            $fact = $extracted->structured['fact'] ?? null;

            if (! is_string($fact) || $fact === '') {
                return;
            }

            $embedding = Embeddings::for([$fact])->generate()->first();

            MemoryFact::create(['content' => $fact, 'embedding' => $embedding]);
        } catch (AiException|HttpClientException) {
            $this->components->warn('Could not save this session to memory. Future sessions will not recall it.');
        }
    }
}
