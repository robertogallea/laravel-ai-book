# Data sent to the model: a review (Chapter 12)

Companion review for Chapter 12's "data sent and disclosure" section: for every command in this
application that calls a model, what data actually leaves the application in that call. Written
before any minimization or disclosure change, so the "before" column below reflects the
application exactly as it stood at the end of Chapter 11.

## What each call actually sends

| Command | Model call | Data sent |
|---|---|---|
| `assistant:ask` | `FinanceAssistant` | The user's typed question, nothing else. |
| `assistant:chat` | `FinanceAssistant` | The user's typed message plus a sliding window of the last 8 messages of this same conversation. Nothing from any other conversation or session. |
| `assistant:log-expense` | `ExpenseExtractor` | The user's typed expense description, nothing else. |
| `assistant:log-expense-from-document` | `ImportedDocumentReader`, then `ExpenseExtractor` | The **entire** imported text (email or document body), unbounded in length, followed by the reader's extracted one-sentence description. |
| `assistant:ask-spending` | `FinanceAssistant` | The user's question plus up to 5 of that user's own transactions (merchant, category, amount, date) that clear a relevance floor against the question. |
| `assistant:ask-with-memory` | `FinanceAssistant`, then `FactExtractor` | The user's question plus up to 3 remembered facts that clear a relevance floor; separately, the same question again, to decide whether it is itself worth remembering. |
| `assistant:assess-purchase` | `PurchaseAdvisor` (tool use) | A one-line goal (amount and description); the agent's own tools return, on request, the account balance (one number), one category's budget status (two numbers), and the full list of recurring subscriptions (name, monthly cost, days unused). |
| `assistant:analyze-spending` | `SpendingAnalyst` | The category name and the amounts already typed on the command line by the caller, nothing pulled from storage. |
| `assistant:generate-monthly-report` | `MonthlyReportSummarizer`, then conditionally `OverspendingAdvisor` | Aggregated totals per budget category (category, amount spent, budget limit); never a single raw transaction. |
| `assistant:convert-currency` | `CurrencyAdvisor` (MCP tool use) | The user's typed question; the MCP tool it may call returns only an exchange rate, no application data. |
| `assistant:eval-categorization`, `assistant:eval-purchase-advisor` | `ExpenseExtractor`, `PurchaseAdvisor` | Fixed, synthetic evaluation cases, never real user data. |

## Conclusion

Most of the calls above already send only what the specific task needs, not because anyone
labeled it "minimization" at the time, but because of choices earlier chapters made for other
reasons: `assistant:ask-spending` and `assistant:ask-with-memory` retrieve a bounded,
relevance-filtered top-N instead of a user's entire history (Chapters 7 and 10); the monthly
report pipeline sends pre-aggregated category totals, never a raw transaction (Chapter 10); every
tool `assistant:assess-purchase` can call returns an aggregate figure or a short list, not a
transaction dump (Chapter 8).

Two gaps remain, neither addressed by any earlier chapter because neither is a security or
resilience concern in the sense those chapters cared about:

1. **No cap on the imported document.** `assistant:log-expense-from-document` sends the entire
   imported text to the model, however long it is, to extract a single sentence out of it. Nothing
   bounds this today: a multi-page statement or a long email thread would be sent to a third-party
   API in full to extract one expense's amount, place, and date.
2. **No disclosure anywhere.** Not one command in this application ever tells the user they are
   talking to an automated system rather than a person. `assistant:chat` prints a plain "Chatting
   with the finance assistant" line; nothing about it says "AI", "automated", or "assistant" in a
   way that reads as a disclosure rather than a command's own label.

Both are addressed in the increment that follows this review.
