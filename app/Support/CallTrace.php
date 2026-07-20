<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Responses\AgentResponse;

/**
 * Records a single completed call to the model as a trace event, so it can
 * be reconstructed later without anyone having to reproduce the same
 * exchange. The event carries the prompt actually sent, the response
 * actually received, the tokens it consumed, the outcome of any guardrail
 * the call went through, when it happened, and, where the caller can
 * resolve one, the user it was made on behalf of. That last field is
 * nullable rather than required: a call site that has not yet been
 * updated to identify its user keeps tracing exactly as before, instead of
 * being forced to invent an identity it does not have.
 */
class CallTrace
{
    public static function record(string $prompt, AgentResponse $response, ?string $guardrailOutcome = null, ?User $user = null): void
    {
        Log::info('llm_call', [
            'prompt' => $prompt,
            'response' => $response->text,
            'tokens' => $response->usage->promptTokens + $response->usage->completionTokens,
            'guardrail_outcome' => $guardrailOutcome,
            'user_id' => $user?->id,
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
