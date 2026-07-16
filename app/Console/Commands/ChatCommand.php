<?php

namespace App\Console\Commands;

use App\Ai\Agents\FinanceAssistant;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Laravel\Ai\Messages\AssistantMessage;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Messages\UserMessage;

use function Laravel\Prompts\info;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\text;

#[Signature('assistant:chat')]
#[Description('Chat with the personal finance assistant about your spending')]
class ChatCommand extends Command
{
    /**
     * Maximum number of past messages kept in the sliding window sent to the model.
     */
    private const MAX_HISTORY_MESSAGES = 8;

    /**
     * Execute the console command.
     *
     * Only the most recent messages are sent on each turn: older ones are
     * dropped from the request once the history exceeds the sliding window.
     */
    public function handle(): void
    {
        info('Chatting with the finance assistant. Type "exit" to end the conversation.');

        /** @var Message[] $history */
        $history = [];

        while (true) {
            $question = text(label: 'You', placeholder: 'Ask about your spending...');

            if (in_array(strtolower(trim($question)), ['', 'exit', 'quit'], strict: true)) {
                break;
            }

            $agent = (new FinanceAssistant)->withHistory($this->slidingWindow($history));

            $response = $agent->prompt($question);

            $this->line($response->text);

            $history[] = new UserMessage($question);
            $history[] = new AssistantMessage($response->text);

            $this->comment(sprintf(
                'history: %d messages (%d sent) | prompt tokens: %d | completion tokens: %d',
                count($history),
                count($this->slidingWindow($history)),
                $response->usage->promptTokens,
                $response->usage->completionTokens,
            ));
        }

        outro('Conversation ended.');
    }

    /**
     * Keep only the most recent messages, dropping older ones once the
     * history grows past the configured window.
     *
     * @param  Message[]  $history
     * @return Message[]
     */
    private function slidingWindow(array $history): array
    {
        return array_slice($history, -self::MAX_HISTORY_MESSAGES);
    }
}
