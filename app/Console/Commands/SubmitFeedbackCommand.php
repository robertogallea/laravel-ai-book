<?php

namespace App\Console\Commands;

use App\Models\EvalFeedback;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('assistant:submit-feedback {description : The expense description that was categorized} {category : The category the assistant returned} {rating : positive or negative}')]
#[Description('Flag a categorization response as satisfactory or not, the same "thumbs up/down" a real interface would show next to it')]
class SubmitFeedbackCommand extends Command
{
    /**
     * Execute the console command.
     *
     * Only a negative rating is queued: a satisfied user confirms nothing
     * that a curated eval case does not already cover, so there is
     * nothing here worth reviewing. See ReviewFeedbackCommand for the
     * review step every negative rating goes through before it can reach
     * the eval dataset: this command never writes to it directly.
     */
    public function handle(): int
    {
        $rating = $this->argument('rating');

        if (! in_array($rating, ['positive', 'negative'], true)) {
            $this->components->error("Rating must be \"positive\" or \"negative\", got \"{$rating}\".");

            return Command::INVALID;
        }

        if ($rating === 'positive') {
            $this->components->info('Thanks for the feedback.');

            return Command::SUCCESS;
        }

        EvalFeedback::create([
            'input' => $this->argument('description'),
            'category' => $this->argument('category'),
            'status' => EvalFeedback::STATUS_PENDING_REVIEW,
        ]);

        $this->components->info('Feedback recorded. This case has been queued for review.');

        return Command::SUCCESS;
    }
}
