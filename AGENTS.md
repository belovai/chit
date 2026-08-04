# AGENTS.md

Guidance for AI coding agents working in this repository.

## What this project is

Chit — self-hosted personal expense tracker. Upload a receipt/invoice
(photo or digital file); the system OCRs it locally for cheap document
classification (receipt vs. utility bill), then sends the normalized
image itself to an LLM for structured extraction (merchant, line items,
quantity, unit price, amount, date, currency) — local OCR text proved
unreliable on digits for some receipt fonts (see
`docs/superpowers/specs/2026-08-02-vision-based-extraction-design.md`),
so the model reads the image directly instead. The result is stored
after a review/approval step. Manually entered items flow into the same
data model as OCR-derived ones, only `source` differs.

Design intent, not to be reverse-engineered from the schema alone:

- **No fixed category list.** A flexible, faceted tag system (module
  `Tag`, `type` + `value`) on line items answers ad-hoc questions later
  ("how much fuel this half-year", "how much at OMV specifically", "how
  many liters") without pre-modeling categories.
- **Multi-currency is not MVP**, but the data model must not need a
  migration for it later (`currency` column exists on line items from the
  start).
- **Provider-agnostic AI layer.** Never hard-couple to one AI vendor.
  `ReceiptExtractorInterface` → `ExtractedReceiptDTO`; Anthropic/OpenAI/local
  (Ollama) are interchangeable implementations. Shared prompt content
  (task description, few-shot examples) is provider-neutral; only the
  structured-output enforcement mechanism (tool_use, json_schema, or
  instruction+repair fallback) is provider-specific.
- **Pending review is mandatory.** OCR+LLM extraction is never 100%
  accurate. Raw OCR text and raw AI response are preserved for audit/debug,
  and nothing becomes a final transaction without going through
  `pending → processing → needs_review → approved/rejected`.
- **Merchant name normalization** is a first-class concern (e.g. "OMV
  Hódmezővásárhely 2" vs "OMV Hmvhely" must resolve to the same merchant),
  otherwise queries fragment.
- **Thin controllers, always.** All query/aggregation/domain logic lives in
  Action/Service classes, called by controllers. This is deliberate because
  a future MCP server will call the same Actions directly, bypassing HTTP —
  the split must exist from day one, not be retrofitted.

Full background/rationale: `CHIT_PROJECT_BRIEF.md` (Hungarian) at the
monorepo root, one level above this repo.

## Current state

Implemented modules: `User`, `Auth`, `Merchant` (+ `MerchantLocation`).
Not yet built: `Tag`, `Transaction`, `Receipt`, `Pipeline`, MCP server —
see brief for their intended shape before creating them from scratch.

Frontend: IA shell in place (dashboard/receipts/transactions/settings
nav), auth flow (login/register), i18n foundation, Merchant settings
CRUD UI. Receipt upload/review UI does not exist yet.

## Architecture

Laravel modular monolith. Each module lives under `modules/<Name>/` and
mirrors a standard Laravel app internally:

```
modules/<Name>/
  Actions/        one class per use-case, `handle()` entry point, `final`
  Controllers/     thin — validate via Request, call one Action, return a Resource
  Requests/        FormRequest validation
  Resources/       API response shaping (JsonResource)
  Models/
  DataTransferObjects/   (only where a module needs one, e.g. Merchant)
  Config/          merged via mergeConfigFrom in the module's provider
  Database/Migrations, Database/Factories, Database/Seeders
  Routes/api.php   loaded via loadRoutesFrom in the module's provider
  Providers/<Name>ModuleServiceProvider.php   registered in bootstrap/providers.php
  Tests/Feature, Tests/Unit
```

Dependency direction is one-way and intentional — do not introduce
reverse or circular dependencies between modules:

`Auth → User`, `Receipt → Pipeline`, `Receipt → Transaction`,
`Receipt → Merchant`, `Transaction → Merchant, Tag`. `Pipeline` has no
domain dependency (image/text in, DTO out) — keep it that way so it stays
independently testable and reusable beyond receipts.

Scaffolding a new module: `./module-helper create <Name> [--db] [--api] [--cmd]`.

### Example Action (style reference)

```php
final class CreateMerchant
{
    /**
     * @param  array{name: string}  $validated
     */
    public function handle(int $ownerId, array $validated): Merchant
    {
        return Merchant::query()->create([
            'owner_id' => $ownerId,
            'name' => $validated['name'],
        ]);
    }
}
```

`declare(strict_types=1)` in every PHP file. Classes `final` unless there's
a specific reason not to.

### Tech stack

- Backend: Laravel 13, PHP 8.4
- Frontend: Vue 3 + TypeScript SPA (`src_frontend/`), Vite, Pinia,
  vue-router, vue-i18n, Tailwind
- DB: PostgreSQL
- Queue/cache/session: Redis + Laravel Horizon (chosen for job
  observability and native rate-limiting of AI API calls — see brief for
  why RabbitMQ was rejected)
- OCR: local (Tesseract or PaddleOCR) for document classification only;
  the extract step sends the normalized image itself to the AI API
  (accuracy over the earlier text-only design — see the vision-extraction
  design doc referenced above)
- Auth: Sanctum (token-based)
- Everything runs in Docker Compose

## Working in this repo

### Backend

- Run everything through the `php` container, not host PHP:
  ```bash
  docker compose exec php php artisan test
  docker compose exec php bash -c "./vendor/bin/pint"
  docker compose exec php ./vendor/bin/phpstan analyse --memory-limit=2G
  docker compose exec php php artisan ide-helper:models -RW --no-interaction
  ```
- PHPStan level 8 (`phpstan.neon`), Pint with `laravel` preset
  (`pint.json`). Both run in the pre-commit hook (`.githooks/pre-commit`,
  wired via `composer post-install-cmd`/`post-update-cmd`) along with
  `ide-helper:models`, and frontend lint/format when `src_frontend/`
  files are staged. Don't bypass the hook.
- Tests are PHPUnit (attributes-based: `#[Test]`), split into
  `modules/*/Tests/Unit` and `modules/*/Tests/Feature` suites
  (`phpunit.xml`). Testing DB is a dedicated Postgres DB
  (`chit_testing`), not sqlite in-memory — see `docker/postgres/init-test-db.sh`.
  Feature tests authenticate via Sanctum token
  (`$user->createToken('api')->plainTextToken` + `Authorization: Bearer`
  header) and use `RefreshDatabase`.
- New module checklist: Action(s) → Controller (thin) → Request →
  Resource → Model/migration/factory → Routes/api.php → register
  provider in `bootstrap/providers.php` → Feature tests. See the
  `laravel-modular-craft` skill for detail when writing new modules.

### Frontend

- `src_frontend/` is a separate npm project. Dev server, lint
  (`oxlint` + `eslint`), format (`prettier`), type-check (`vue-tsc`) all
  run via `npm run <script>` inside the `node` container in Docker Compose
  — don't spawn a second host-level dev server.
  - Preview at `http://localhost` (not the `nip.io` URL that appears in
    `DEVELOPMENT.md`).
- Route structure: `AppShell.vue` wraps authenticated routes
  (`meta: { requiresAuth: true }`); guest-only routes (`login`,
  `register`) use `meta: { guestOnly: true }`. Guard logic lives in
  `router/index.ts` via `useAuthStore()`.
- i18n: `vue-i18n`, locale files under `src/locales/en/` split by domain
  (`fields.ts`, `validation.ts`, `merchant.ts`, `common.ts`, etc.),
  aggregated in `src/locales/en/index.ts`.
- UI primitives live in `src/components/ui/` (`AppButton.vue`,
  `FormField.vue`, `ConfirmDialog.vue`). No native `window.confirm`/
  `alert` — use `ConfirmDialog` (or ask before adding a new native-dialog
  fallback).

### Test login (local/dev)

`sysadmin@example.com` / `password` — reseed via `UserSeeder` if the DB is
fresh.

## Conventions agents must not violate

1. Controllers stay thin — no query/business logic inline; put it in an
   Action or Service, since the planned MCP server will call Actions
   directly, not controllers.
2. Don't hardcode a fixed category enum for expenses — that's the tag
   system's job.
3. Don't couple pipeline/extraction code to one AI vendor's SDK/response
   shape directly — go through the `ReceiptExtractorInterface` /
   `ExtractedReceiptDTO` abstraction (once the `Pipeline` module exists).
4. Preserve raw OCR text and raw AI responses when building the
   `Receipt` review flow — needed for audit/debugging hallucinated
   extractions.
5. Never commit without an explicit user request, even mid-workflow.
