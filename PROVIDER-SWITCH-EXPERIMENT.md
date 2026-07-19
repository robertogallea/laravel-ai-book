# Provider switch experiment (Chapter 10)

Companion experiment for Chapter 10's "cost of switching providers" section: swapped this app's
configured AI provider from `openai` (`config('ai.default')`, `config('ai.default_for_embeddings')`)
to `anthropic`, one of `laravel/ai`'s other bundled providers, and re-ran the entire existing test
suite unchanged, to see what continued to work and what did not.

No real Anthropic API key is configured in this repository (only `OPENAI_API_KEY` is), so this
experiment observes what breaks *before* a single real network call is made: interface checks,
model-name resolution, and anything the test suite exercises through `Agent::fake()` /
`Embeddings::fake()`. Genuine rate-limit and output-behavior differences require live traffic
against a real, credentialed provider and could not be observed here, see "What this experiment
could not test" below.

## Result summary

93 tests before the switch. After changing both `ai.default` and `ai.default_for_embeddings` to
`anthropic` and re-running the exact same suite, unmodified: 81 passed, 12 failed (3 assertion
failures, 9 uncaught exceptions). Reverting the two config values restores 93/93 with no other
change.

## What continued to work unchanged

Every command whose interaction with the provider is limited to plain text generation kept passing
without a single line of application code touched: streaming chat, the structured system prompt,
guardrailed document import, the human-in-the-loop approval flow, the resilience/retry-backoff
command, the agentic purchase-assessment loop (tool use, `MaxSteps`), the MCP-based currency
conversion, and the orchestrated monthly-report pipeline. `laravel/ai`'s common interface held up
exactly as advertised for this category of interaction.

## What broke, by category

### Parita di funzionalita (the category that actually broke)

`AnthropicProvider` does not implement `Laravel\Ai\Contracts\Providers\EmbeddingProvider` (unlike
`OpenAiProvider`): Anthropic has no embeddings API for `laravel/ai` to wrap. Every code path that
calls `Embeddings::for(...)->generate()` throws:

```
LogicException: Provider [Laravel\Ai\Providers\AnthropicProvider] does not support embedding generation.
```

This is thrown by the package itself (`Laravel\Ai\AiManager::embeddingProvider()`), before any
network request, and *even when embeddings are faked in a test* (`Embeddings::fake()`): the package
checks `instanceof EmbeddingProvider` unconditionally, ahead of deciding whether to use a real or a
fake gateway. Concretely, this broke:

- `IndexTransactionsCommand` (Chapter 7): every transaction-indexing test failed.
- `AskSpendingCommand` (Chapter 7): every test that reaches the embedding step failed.
- `AskWithMemoryCommand` (Chapter 10): every test that reaches either the retrieval or the
  extraction-and-persist step failed, since both compute an embedding.

None of these commands anticipated this failure: each only catches `Laravel\Ai\Exceptions\AiException`
and `Illuminate\Http\Client\HttpClientException` around the embeddings call, both of which assume
the provider *attempted* the call and failed technically. `LogicException` is neither: it signals
that the configured provider was never capable of this operation in the first place, a
configuration-time mismatch, not a runtime failure, and it propagates uncaught. This is exactly the
"parita di funzionalita non garantita" the chapter's narrative section names in the abstract, made
concrete: RAG on transactions and long-term memory (Chapters 7 and 10) both silently assumed the
configured provider supports embeddings, an assumption that held for every provider used so far
only because it was never actually tested against one that does not.

### Model routing (a related, narrower symptom, not a new category)

`ModelRoutingTest`'s two assertions comparing the resolved model against the literal strings
`gpt-5.4-nano` and `gpt-5.4-pro` fail after the switch, because `AnthropicProvider` resolves
`#[UseCheapestModel]` / `#[UseSmartestModel]` to `claude-haiku-4-5-20251001` and `claude-opus-4-8`
instead. This is not a bug in the routing mechanism, which keeps working exactly as designed
(`ExpenseExtractor` and `PurchaseAdvisor` still resolve to two *different* models, each still the
cheapest/smartest available on the configured provider); it only shows that hardcoding a concrete
resolved model name in a test, rather than asserting the two models differ from each other, quietly
bakes in an assumption about which provider is configured.

## What this experiment could not test

Rate limits and subtle output-behavior differences at the same prompt, the other two categories the
chapter's narrative describes, require real, credentialed traffic against the alternative provider:
rate limits only manifest under real request volume, and output differences only manifest when a
real model actually generates a real response. This repository has no Anthropic credentials
configured, and the entire existing test suite is deliberately provider-oblivious by design
(`Agent::fake()`, `Embeddings::fake()`): that is precisely what makes it fast and deterministic, and
precisely what makes it structurally unable to observe either of these two categories. A provider
switch that only broke in one of these two ways would pass every test in this suite without a
single failure, exactly as convincingly as the "everything else continued to work" result reported
above.

## Unanticipated observation

The most useful finding was not a difference in behavior, but a difference in *when* the failure
surfaces: the embeddings gap is caught by the package itself, synchronously and specifically,
before any network call, which means it would have been caught immediately in any environment,
including this fully-faked test suite, the moment the provider was switched. The other two
categories this chapter discusses would not be caught this way, by this suite, no matter how
thorough its coverage of application logic is: they live entirely in what happens *after* a real
network call this suite deliberately never makes.
