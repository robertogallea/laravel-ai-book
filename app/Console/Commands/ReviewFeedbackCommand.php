<?php

namespace App\Console\Commands;

use App\Ai\Agents\ExpenseExtractor;
use App\Models\EvalFeedback;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\select;

#[Signature('assistant:review-feedback')]
#[Description('Review pending feedback and confirm which flagged cases belong in the categorization eval set')]
class ReviewFeedbackCommand extends Command
{
    /**
     * Execute the console command.
     *
     * The same principle already applied to the approval flow in Chapter
     * 5: a signal from outside the application, here a user's negative
     * rating, never takes effect on its own. Each pending entry is shown
     * to a reviewer, who confirms it is a genuine miscategorization
     * rather than a misunderstanding, then supplies the category the
     * response should have returned. Only a confirmed entry is picked up
     * by EvalCategorizationCommand; a dismissed one stays out for good.
     */
    public function handle(): int
    {
        $pending = EvalFeedback::where('status', 'pending_review')->get();

        if ($pending->isEmpty()) {
            $this->components->info('No feedback pending review.');

            return Command::SUCCESS;
        }

        if (! $this->input->isInteractive()) {
            $this->components->error('Reviewing feedback requires an interactive terminal. Nothing was reviewed.');

            return Command::FAILURE;
        }

        foreach ($pending as $feedback) {
            $this->components->info("Flagged expense: \"{$feedback->input}\"");
            $this->line("Assistant returned category: {$feedback->category}");

            if (! confirm(label: 'Is this a genuine miscategorization?', default: true)) {
                $feedback->update(['status' => 'dismissed']);
                $this->components->warn('Dismissed: not added to the eval dataset.');

                continue;
            }

            $expected = select(
                label: 'What is the correct category?',
                options: ExpenseExtractor::CATEGORIES,
            );

            $feedback->update(['status' => 'confirmed', 'expected_category' => $expected]);
            $this->components->info("Confirmed: added to the eval dataset as \"{$expected}\".");
        }

        return Command::SUCCESS;
    }
}
