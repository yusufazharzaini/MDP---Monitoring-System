# Material Delivery Performance Monitoring System — Architecture

## 1. Purpose

Monitor material delivery performance from suppliers into plants: purchase orders,
deliveries, receiving, late/short delivery, supplier KPI, problem analysis,
corrective action, dashboard and reporting.

Every number rendered on the dashboard is derived from transactional data in MySQL.
No metric is hard-coded in the frontend.

## 2. Stack

| Layer | Technology |
|---|---|
| Backend | Laravel 13.x, PHP 8.4 |
| Database | MySQL 8 (production), SQLite in-memory (test suite) |
| Auth | Session auth for Inertia; no token/API surface is installed |
| Authorization | spatie/laravel-permission + Laravel Policies |
| Frontend | Vue 3 (`<script setup lang="ts">`), Inertia.js 3, Pinia, Tailwind CSS 4 |
| Charts | Apache ECharts 6 |
| Export | maatwebsite/excel (xlsx), barryvdh/laravel-dompdf (pdf) |
| Async | Laravel Queue, Events/Listeners, Notifications |
| Testing | PHPUnit 12 (Feature / Unit / Database) |

## 3. Request flow

```
Vue 3 page (resources/js/Pages)
  -> Inertia visit
  -> routes/web.php  (auth + permission middleware)
  -> Controller (thin: authorize, validate, delegate, respond)
  -> Form Request (validation + authorization rules)
  -> Service (business logic, transactions, orchestration)
  -> Repository (only where query composition is non-trivial: dashboard, reports, performance)
  -> Eloquent Model
  -> MySQL
```

**Rule:** controllers contain no business logic. They authorize, hand a validated DTO
to a service, and return an Inertia response or redirect.

**Repository usage:** we do *not* wrap trivial CRUD in repositories. Repositories exist
only where a query object genuinely pays for itself:

- `DashboardRepository` — aggregate KPI/trend/pareto/ranking queries
- `SupplierPerformanceRepository` — scorecard + ranking aggregates
- `ReportRepository` — export dataset queries

Everything else uses the Service Layer directly over Eloquent.

## 4. Directory layout

```
app/
├── Actions/            # single-purpose invokable units (number generators, recalculations)
├── Console/Commands/   # scheduled maintenance commands
├── DataTransferObjects/# DashboardFilter and friends (immutable, readonly)
├── Enums/              # backed enums for every status/severity/type
├── Events/             # DeliveryReceived, ProblemReported, PurchaseOrderApproved...
├── Exceptions/         # BusinessRuleException and domain errors
├── Exports/            # Laravel Excel export classes
├── Http/
│   ├── Controllers/    # thin controllers, grouped by module
│   ├── Middleware/      # HandleInertiaRequests, audit context
│   ├── Requests/       # Form Request validation
│   └── Resources/      # API resources for the JSON contract
├── Jobs/               # queued work (imports, evaluation recalculation)
├── Listeners/          # react to events (audit, notification)
├── Models/             # Eloquent models
├── Notifications/      # Laravel notifications
├── Observers/          # audit-log observers
├── Policies/           # per-model authorization
├── Repositories/       # aggregate query objects (dashboard/performance/report only)
├── Services/           # business logic per module
└── Support/            # small shared helpers
```

## 5. Modules

1. Authentication  2. Dashboard  3. Supplier  4. Supplier Contact  5. Plant
6. Warehouse  7. Material  8. Material Category  9. UOM  10. Department
11. Purchase Order  12. PO Items  13. Delivery  14. Delivery Items
15. Problem Analysis  16. Problem Category  17. Problem Attachment
18. Corrective Action  19. Supplier Performance  20. Supplier Evaluation
21. KPI Settings  22. Critical Material  23. Notification  24. Report
25. User Management  26. Role & Permission  27. Audit Log  28. System Settings

## 5b. Language

The interface is offered in English (default), Indonesian, Japanese and Chinese
(Simplified). Two things are translated, and one deliberately is not.

**Translated — interface labels.** `lang/<locale>/ui.php`. The whole file for
the active locale is handed to the browser through Inertia's shared props, so
one language crosses the wire rather than four, and `useTranslate()` reads it. No
i18n library sits behind that: Laravel's lang files already resolve validation
messages and notifications server-side, and a second dictionary in JavaScript
would be a second place for a translation to go stale.

**Translated — the enumerated vocabulary.** `lang/<locale>/enums.php`, resolved
by `HasEnumMetadata::label()`. One lookup point covers all 19 enums and 86
cases, so a status badge arrives at the browser already in the reader's
language. A missing key falls back to title-cased English rather than rendering
`enums.ProblemSeverity.HIGH` on a dashboard.

**Not translated — anything a person typed.** Supplier names, material codes,
purchase order numbers, problem descriptions and corrective actions are the
record this system is audited against. Machine-translating them would mean two
operators reading two different versions of one row, and a corrective action
whose wording no longer matches what was written. `LocaleTest` asserts this
directly.

| Concern | Where it is decided |
|---|---|
| Which language a request renders in | `SetLocale` middleware: account choice, then session, then `APP_LOCALE`. Registered before Inertia so shared props are built in the right language. |
| Where the choice is stored | `users.locale`, nullable — null means "follow the application default". The session covers the login screen, where there is no account yet. |
| Changing it | `POST /locale`, deliberately outside the auth group: somebody who cannot read the login screen must be able to change it before signing in. |
| Printed documents | Pinned to `config('locales.documents')`. DomPDF renders with DejaVu Sans, which carries **no CJK glyphs** - a Japanese PDF would come out as empty boxes rather than as an error. Both PDF and the printable HTML use it, so a filed report is the same document whoever exported it. Excel is unaffected; PhpSpreadsheet writes UTF-8. |

Adding a language means a row in `config/locales.php` and a directory under
`lang/`. `LocaleTest` fails if that directory is missing any key English
defines, or leaves one blank, so a half-finished translation cannot ship
quietly.

## 6. Documented assumptions

Where the specification left a detail open, these decisions were made and are
implemented consistently across schema, services, tests and UI.

| # | Ambiguity | Decision |
|---|---|---|
| A1 | What is one "delivery" for the *Total Delivery* KPI? | A **delivery line** (`delivery_items` row). A DO containing 3 materials is 3 delivery records for KPI purposes, because timeliness and quantity are only meaningful per material against its own PO schedule. |
| A2 | Timeliness reference date | `deliveries.delivery_date` (actual receipt) vs `purchase_order_items.schedule_delivery_date` of the linked PO item. Comparison is date-only, `<=` is ON_TIME. |
| A3 | Line-level quantity status with split deliveries | Cumulative: for each delivery line, compare *cumulative received for its PO item up to and including that line* against `qty_ordered`. A first partial shipment is therefore SHORT, and the shipment that completes the item is FULL. |
| A4 | PO-item level status (§13) | Stored denormalized on `purchase_order_items` (`qty_received`, `fulfillment_status`, `timeliness_status`, `overall_status`) so the PO Monitoring table and Critical Material queries are indexable aggregates, never PHP loops. |
| A5 | Cancelled deliveries | Excluded from all KPI aggregates and from `qty_received` rollups. |
| A6 | Notifications table | Laravel's native `notifications` schema (uuid, notifiable morph, `data` json, `read_at`) is used, because Laravel Notifications requires it. `title`, `message`, `severity` and `url` live inside `data`; `read_at` replaces `is_read`. |
| A7 | Supplier grade thresholds | Read from `kpi_settings` (`GRADE_EXCELLENT`=98, `GRADE_GOOD`=95, `GRADE_AVERAGE`=90). Never hard-coded in Vue. |
| A8 | Service Rate formula | Strategy driven by `system_settings`: `service_rate.formula` = `on_time` (default) or `weighted`; weights `service_rate.weight_on_time` / `service_rate.weight_quantity`. Both implementations ship. |
| A9 | Public identifiers | Transactional and master entities expose a `ulid` column used as the route key, so database IDs are never in URLs. |
| A10 | Delivery header `status` | Operational lifecycle (PENDING/RECEIVED/PARTIAL/COMPLETED/CANCELLED), recalculated from its lines. Performance status is *never* stored on the header. |
| A11 | Currency | Stored per PO as ISO code; amounts are `decimal(18,4)`. No FX conversion in scope. |
| A12 | Over-delivery tolerance | `system_settings.delivery.over_tolerance_percent` (default 0) — receipts within tolerance count as FULL, not OVER. |
| A13 | Corrective action identifiers | Corrective actions have no `ulid`. They are only ever addressed inside a problem's own ULID URL, and the controller refuses an action belonging to another problem, so the numeric id reveals nothing reachable. |
| A15 | Evaluation permissions | The specification's §22 permission list has no evaluation module. Approving a supplier's monthly grade is a management judgement rather than a reporting action, and `report.view` reaches every VIEWER, so `evaluation.view` / `.create` / `.approve` were added rather than conflating sign-off with report access. |
| A14 | Overdue notification shape | The requirement says an overdue problem triggers a notification; it is delivered as one daily digest per recipient rather than one message per problem, because a supervisor with a backlog needs a queue to work, not a mailbox to clear. |
