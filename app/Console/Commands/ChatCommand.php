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
     * Execute the console command.
     *
     * Every turn is sent together with the entire conversation history so far:
     * there is no limit on how large that history is allowed to grow.
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

            $agent = (new FinanceAssistant)->withHistory($history);

            $response = $agent->prompt($question);

            $this->line($response->text);

            $history[] = new UserMessage($question);
            $history[] = new AssistantMessage($response->text);

            $this->comment(sprintf(
                'history: %d messages | prompt tokens: %d | completion tokens: %d',
                count($history),
                $response->usage->promptTokens,
                $response->usage->completionTokens,
            ));
        }

        outro('Conversation ended.');
    }
}
