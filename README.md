# Chit

> ⚠️ **Hobby project, heavily under development.** Built for personal use and
> for fun. Expect breaking changes, missing pieces, and rough edges — no
> stability or compatibility promises at this stage.

Chit is a self-hosted personal expense tracker built around receipt/invoice
uploads instead of manual spreadsheet entry. The idea: snap a photo of a
receipt, let the app OCR and extract the structured data (merchant, line
items, quantities, prices, date, currency), review/approve it, and it becomes
a transaction. Manual entry is also supported and lands in the same data
model.

## Why

Spreadsheets for expense tracking don't stick — the friction of manual entry
kills the habit. Chit tries to remove that friction on the input side while
keeping the data model flexible enough to answer questions you didn't think
to plan for (e.g. "how much did I spend at this gas station this year", "how
many liters of fuel in total"), via a flexible, faceted tagging system
instead of a fixed category list.

## Planned architecture

- **Backend**: Laravel, modular monolith (`modules/<Name>` convention)
- **Frontend**: Vue 3 + TypeScript SPA
- **Database**: PostgreSQL
- **Queue/cache**: Redis + Laravel Horizon
- **OCR**: local (Tesseract/PaddleOCR) — no raw images sent to AI providers
- **AI/LLM layer**: provider-agnostic (Anthropic/OpenAI/local model), used
  only to turn OCR text into structured data
- **Environment**: Docker / Docker Compose

Planned modules: `User` (done), `Auth`, `Merchant` (normalization/fuzzy
matching), `Tag` (faceted labeling), `Transaction`, `Receipt` (upload +
review/approval state machine), `Pipeline` (OCR + AI extraction, stateless).
Further down the line: an MCP server exposing the transaction/query layer to
AI assistants, and proper multi-currency support.

## Status

Early days. `User`, `Auth`, and `Merchant` modules exist; the receipt
upload/OCR/AI pipeline and transaction tracking itself are not built yet.

## Local development

See [DEVELOPMENT.md](DEVELOPMENT.md).
