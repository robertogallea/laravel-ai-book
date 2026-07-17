<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Laravel\Ai\Responses\AgentResponse;

/**
 * Records a single completed call to the model as a trace event, so it can
 * be reconstructed later without anyone having to reproduce the same
 * exchange. The event carries exactly five fields on purpose: the prompt
 * actually sent, the response actually received, the tokens it consumed,
 * the outcome of any guardrail the call went through, and when it
 * happened. No request or user identifier is included here: correlating a
 * trace back to who triggered it is a distinct concern, addressed where
 * the application starts serving more than one user at a time.
 */
class CallTrace
{
    public static function record(string $prompt, AgentResponse $response, ?string $guardrailOutcome = null): void
    {
        Log::info('llm_call', [
            'prompt' => $prompt,
            'response' => $response->text,
            'tokens' => $response->usage->promptTokens + $response->usage->completionTokens,
            'guardrail_outcome' => $guardrailOutcome,
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
