<?php

namespace App\Console\Commands;

use App\Ai\Agents\FinanceAssistant;
use App\Console\Commands\Concerns\DisclosesAiInteraction;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Laravel\Ai\Messages\AssistantMessage;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Messages\UserMessage;
use Laravel\Ai\Streaming\Events\TextDelta;

use function Laravel\Prompts\info;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\text;

#[Signature('assistant:chat')]
#[Description('Chat with the personal finance assistant about your spending')]
class ChatCommand extends Command
{
    use DisclosesAiInteraction;

    /**
     * Maximum number of past messages kept in the sliding window sent to the model.
     */
    private const MAX_HISTORY_MESSAGES = 8;

    /**
     * Execute the console command.
     *
     * Only the most recent messages are sent on each turn: older ones are
     * dropped from the request once the history exceeds the sliding window.
     * The reply is streamed, so it is printed chunk by chunk as it arrives
     * instead of only once the model has finished generating it.
     */
    public function handle(): void
    {
        $this->discloseAiInteraction();

        info('Chatting with the finance assistant. Type "exit" to end the conversation.');

        /** @var Message[] $history */
        $history = [];

        while (true) {
            $question = text(label: 'You', placeholder: 'Ask about your spending...');

            if (in_array(strtolower(trim($question)), ['', 'exit', 'quit'], strict: true)) {
                break;
            }

            $agent = (new FinanceAssistant)->withHistory($this->slidingWindow($history));

            $stream = $agent->stream($question);

            foreach ($stream as $event) {
                if ($event instanceof TextDelta) {
                    $this->output->write($event->delta);
                }
            }

            $this->newLine();

            $history[] = new UserMessage($question);
            $history[] = new AssistantMessage($stream->text);

            $this->comment(sprintf(
                'history: %d messages (%d sent) | prompt tokens: %d | completion tokens: %d',
                count($history),
                count($this->slidingWindow($history)),
                $stream->usage->promptTokens,
                $stream->usage->completionTokens,
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
