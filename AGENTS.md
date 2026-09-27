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
  migration for it later (`currency` column exists on `transactions` from
  the start; line items inherit it).
- **Provider-agnostic AI layer.** Never hard-couple to one AI vendor.
  Two layers: `Ai` (`AiProvider`/`AiClient` contracts, per-user
  credentials, usage + cost logging) and `Extraction` (`DocumentClassifier`
  / `DocumentExtractor` → `ExtractedReceipt` / `ExtractedBill`).
  Anthropic/OpenAI/local (Ollama) are meant to be interchangeable providers
  — only Anthropic is implemented so far. Shared prompt content
  (task description, few-shot examples) is provider-neutral; only the
  structured-output enforcement mechanism (tool_use, json_schema, or
  instruction+repair fallback) is provider-specific.
- **Pending review is mandatory.** OCR+LLM extraction is never 100%
  accurate. Raw OCR text and raw AI response are preserved for audit/debug
  (as pipeline artifacts), reviewer edits are recorded in
  `receipt_corrections`, and nothing becomes a final transaction without going through
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

Implemented modules:

- `User`, `Auth` — account, Sanctum auth, profile/password edit, account
  deletion with background purge.
- `Merchant` (+ `MerchantLocation`) — fuzzy matching via `pg_trgm`,
  address normalization for location matching.
- `Product` — owner-scoped product catalog that line items resolve to
  (same candidate-matching pattern as `Merchant`). Not in the original
  brief.
- `Transaction` — CRUD for transactions + line items (`source`:
  `manual`/`receipt`). No query/aggregation Actions yet.
- `Ai` — provider-agnostic AI client layer, per-user credentials
  (verify/activate/suspend), usage and cost logging. Only the Anthropic
  provider exists.
- `Extraction` — stateless OCR (Tesseract) + AI classification/extraction.
  This is what the brief called `Pipeline`.
- `Pipeline` — stateful, generic step engine (runs, steps, artifacts,
  gates, retry/resume/cancel) with its own API and UI. Domain-agnostic.
- `Receipt` — upload, the `receipt_ingest` pipeline definition and its
  steps (store, dedupe, preprocess, OCR, classify, extract, match
  merchant/location/products, validate, review gate, create transaction),
  review flow, corrections. Handles two document types: receipt and
  utility bill (`series_key` links recurring bills).

Not yet built: `Tag` (backend and UI — `SettingsTagsView` is a
placeholder), query/aggregation Actions and transaction filtering,
dashboard data (placeholder), real rate limiting on the `pipeline-ai`
queue (only a concurrency cap today), non-Anthropic AI providers, MCP
server, multi-currency conversion. See the brief for the intended shape
of `Tag` before creating it.

Frontend: app shell (dashboard/receipts/transactions/settings), auth,
i18n, receipt upload + review, pipeline run list/detail, transaction
list/detail/manual entry, settings for account, merchants (+ locations),
products and AI credentials.

Known debt: several modules still predate the current PHP conventions
(`docs/module-conventions-migration.md`); some controllers still hold
query logic (e.g. `TransactionController`, `HeartbeatController`).

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

`Auth → User`, `Extraction → Ai`,
`Receipt → Pipeline, Extraction, Ai, Transaction, Merchant, Product`,
`Transaction → Merchant, Product` (and `Tag`, once it exists). Any module
may depend on `User`. `Pipeline` and `Extraction` have no domain
dependency — keep it that way so they stay independently testable and
reusable beyond receipts.

Known exceptions to fix, not to copy: `Auth` (`HeartbeatController`)
reads `Receipt`, and `UserSeeder` calls into `Ai`.

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

### PHP and Laravel conventions

`modules/Ai` is the reference implementation — when in doubt, copy what it
does. New code follows these; old code gets migrated (see
`docs/module-conventions-migration.md`).

**Immutability.** A class whose state is fixed at construction is
`final readonly class`, and its promoted parameters are plain `private` —
not `private readonly` inside a mutable class:

```php
final readonly class CostCalculator
{
    public function __construct(private ProviderRegistry $providers) {}
}
```

Drop `readonly` from the class only when it genuinely holds mutable state
(e.g. a rule that receives the validator via `ValidatorAwareRule`).

**`#[\Override]`** on every method that implements an interface or overrides
a parent: `toArray()` on Resources, `messages()`/`prepareForValidation()` on
FormRequests, `casts()` on Models, `validate()` on Rules, contract methods on
providers and clients. Not on `rules()` — FormRequest does not declare it.

**Eloquent through attributes, not properties.** `#[Fillable(...)]` and
`#[Hidden(...)]` on the class; scopes are `#[Scope]` on a *protected* method
named for the scope, not a public `scopeXxx`:

```php
#[Hidden('api_key', 'key_fingerprint')]
final class AiCredential extends Model
{
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
```

Cast names are spelled out (`'integer'`, `'boolean'`), and the `@property`
docblock is generated by `ide-helper:models -RW` — never hand-write or
duplicate it.

**`#[\SensitiveParameter]`** on any parameter carrying a secret, so it stays
out of stack traces.

**Enums** are backed, and `use EnumCompares` (`app/Traits/EnumCompares.php`)
so comparisons read as `$status->in([...])` / `->equals()` rather than
`in_array($status, [...], true)`.

**PHP 8.4 idioms**: `array_find()` / `array_any()` instead of a foreach with a
flag; `new Client(...)->method()` without the wrapping parentheses.

**Docblocks** import class names (`AiCredentialFactory`), never inline FQNs.
Add `@throws` where a throwable escapes the method (`DB::transaction`, etc.).

**Validation** never puts a DB check in a closure inside a FormRequest — it
goes in a `Modules/<Module>/Rules/` class. Messages are stable machine codes
namespaced by module (`ai.duplicate_key`, `product.duplicate_name`), never
English prose: the frontend resolves them through `src/locales/en/<module>.ts`
(singular file = codes, plural = view copy). Report a per-field problem on the
precise attribute (`settings.max_tokens`), not the parent.

**Failures that cross a vendor boundary are classified where they are
thrown.** The adapter that knows the vendor's error vocabulary sets the kind
(`AiException::authFailure()`, `::unusable()`); nothing downstream inspects an
exception message to work out what happened.

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
   shape directly — go through the `Ai` contracts and the `Extraction`
   module (`DocumentClassifier` / `DocumentExtractor`).
4. Preserve raw OCR text and raw AI responses when building the
   `Receipt` review flow — needed for audit/debugging hallucinated
   extractions.
5. Never commit without an explicit user request, even mid-workflow.
