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

## Chapter 1 - assistant:chat

```bash
php artisan assistant:chat
```

An interactive, in-memory conversation with the personal finance assistant (no persistence
across sessions, no tools, no retrieval: those are covered in later chapters). Ask a few
consecutive questions about your spending in the same run to see the conversation history grow.

Tags for this chapter:

- `ch01-unbounded-history` - the full conversation history is resent on every turn, without limit.
- `ch01-sliding-window-context` - only the most recent messages are kept and resent (see
  `app/Console/Commands/ChatCommand.php`); compare the two with:

  ```bash
  git diff ch01-unbounded-history ch01-sliding-window-context
  ```

## Tag convention

Tags follow `chNN-slug`, where `NN` is the two-digit chapter number. Multiple tags are added per
chapter as the prose introduces new code increments, not one tag per chapter.
