# Companion app

This is the companion application for the book "Come funzionano le API degli LLM" and the
chapters that follow it. It is a Laravel application that grows chapter by chapter alongside
the book's prose; each chapter's code increment is marked with a Git tag in this repository.

This is an independent Git repository, nested inside the book's repository but not tracked by
it (see the root `.gitignore`).

## Reference versions

- PHP 8.5
- Laravel 13
- [`laravel/ai`](https://packagist.org/packages/laravel/ai) `^0.9`, default provider: OpenAI

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Set your `OPENAI_API_KEY` in `.env` (see `config/ai.php` for all supported providers if you want
to use a different one).

## Chapter 1 - assistant:ask and assistant:chat

```bash
php artisan assistant:ask "How much did I spend on restaurants this month?"
```

The simplest possible call to the assistant: one question, one synchronous reply, no history.
This is the reference example later chapters build on.

```bash
php artisan assistant:chat
```

An interactive, in-memory conversation with the personal finance assistant (no persistence
across sessions, no tools, no retrieval: those are covered in later chapters). Ask a few
consecutive questions about your spending in the same run to see the conversation history grow,
and watch the reply stream in chunk by chunk instead of appearing all at once.

Tags for this chapter:

- `ch01-first-call` - the `assistant:ask` baseline: a single non-conversational call.
- `ch01-unbounded-history` - the full conversation history is resent on every turn, without limit.
- `ch01-sliding-window-context` - only the most recent messages are kept and resent (see
  `app/Console/Commands/ChatCommand.php`); compare the two with:

  ```bash
  git diff ch01-unbounded-history ch01-sliding-window-context
  ```
- `ch01-blocking-chat` - `assistant:chat` still waits for the full reply before printing anything.
- `ch01-streaming-chat` - the same command prints the reply progressively as chunks arrive;
  compare the two with:

  ```bash
  git diff ch01-blocking-chat ch01-streaming-chat
  ```

## Chapter 2 - the system prompt as a structured artifact

The assistant's system prompt (`app/Ai/Agents/FinanceAssistant.php`) started, in Chapter 1, as a
short generic sentence. This chapter turns it into the running example of the chapter's own
subject: a prompt built deliberately, section by section, instead of written off the cuff.

Try the same handful of questions against both tagged versions, using `assistant:chat` so you can
mention some fictitious spending figures first (there is still no transaction data or tool access
at this point in the book: the assistant only knows what you tell it in the conversation):

- "I spent $184 on restaurants this month, and $95 on groceries. How much did I spend in total?"
- "How much did I spend on restaurants this month?" (in-scope, but check the tone and structure of
  the reply)
- "Can you help me write a Python script instead?" (out of scope: watch whether the assistant
  declines and stays on topic, or wanders off with the generic prompt)
- "Should I invest my savings in index funds?" (should be declined either way, but only the
  structured prompt says so explicitly instead of just improvising an answer)

Tags for this chapter:

- `ch02-generic-instructions` - a single ambiguous sentence, with no guidance on tone, format, or
  what to do when unsure or out of scope. Expect replies that drift in tone and structure from one
  question to the next, and no consistent boundary around personal-finance topics.
- `ch02-structured-instructions` - the same prompt rewritten as a sectioned template (role and
  scope, tone, response format, behavior under uncertainty, one worked example), the same shape
  introduced in the book's chapter. Compare the two with:

  ```bash
  git diff ch02-generic-instructions ch02-structured-instructions
  ```

## Tag convention

Tags follow `chNN-slug`, where `NN` is the two-digit chapter number. Multiple tags are added per
chapter as the prose introduces new code increments, not one tag per chapter.
