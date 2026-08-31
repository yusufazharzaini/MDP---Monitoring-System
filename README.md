# Material Delivery Performance Monitoring System

Internal system for monitoring material delivery performance from suppliers into
plants: purchase orders, receiving, late and short delivery, supplier KPI,
problem analysis, corrective action, dashboard and reporting.

Built for PT. Torica Indonesia.

---

## Stack

Laravel 13 · PHP 8.4 · MySQL 8 · Inertia.js · Vue 3 (TypeScript) · Pinia ·
Tailwind CSS 4 · Apache ECharts · Laravel Sanctum · spatie/laravel-permission ·
Laravel Excel · DomPDF · PHPUnit 12

## Documentation

| Document | Contents |
|---|---|
| [docs/01-ARCHITECTURE.md](docs/01-ARCHITECTURE.md) | Layering, directory map, modules, documented assumptions |
| [docs/02-ERD.md](docs/02-ERD.md) | **Production ERD**: Mermaid diagrams, full data dictionary, relationships and cascade rules, migration order, model relationships, index recommendations, business constraints, and a validation of every supported scenario |
| [docs/03-BUSINESS-RULES.md](docs/03-BUSINESS-RULES.md) | Status enums, delivery calculation, KPI formulas, lifecycles |
| [docs/04-ROUTE-MAP.md](docs/04-ROUTE-MAP.md) | Route map, dashboard JSON contract, service/component inventory, roadmap |
| [docs/05-DEMO-DATA.md](docs/05-DEMO-DATA.md) | What the seed reproduces and how |

## Requirements

- PHP >= 8.3 with `pdo_mysql`, `mbstring`, `intl`, `gd`, `zip`
- Composer 2
- Node 20+ and npm
- MySQL 8 (SQLite is used for the test suite)

## Getting started

```bash
git clone <repository-url> mdp-monitoring
cd mdp-monitoring

composer install
npm install

cp .env.example .env
php artisan key:generate
```

Point `.env` at your database:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=mdp_monitoring
DB_USERNAME=root
DB_PASSWORD=
```

Then create the schema and the demo dataset:

```bash
php artisan migrate:fresh --seed
php artisan storage:link
```

Run the app:

```bash
php artisan serve      # http://127.0.0.1:8000
npm run dev            # Vite dev server, in a second terminal
```

Sign in with any seeded account (see
[docs/05-DEMO-DATA.md](docs/05-DEMO-DATA.md#6-demo-accounts)); the password
comes from `MDP_DEMO_PASSWORD` and defaults to `password123`.

### Running on SQLite instead

```dotenv
DB_CONNECTION=sqlite
# leave DB_DATABASE commented out
```

```bash
touch database/database.sqlite
php artisan migrate:fresh --seed
```

## Testing

```bash
php artisan test                    # whole suite
php artisan test --testsuite=Unit   # pure business rules, no database
./vendor/bin/pint --test            # PSR-12 / Laravel style
npm run typecheck                   # vue-tsc
npm run build                       # production assets
```

The suite runs against SQLite in memory — no database setup required.

Two tests are worth knowing about:

- `DeliveryCalculationTest` covers the four business-rule cases from the
  specification (ON_TIME_FULL, LATE_FULL, ON_TIME_SHORT, LATE_SHORT).
- `DemoDataIntegrityTest` asserts the seeded data reproduces the reference
  dashboard exactly — KPI cards, supplier ranking, trend line and Pareto.

## Project layout

```
app/
├── Enums/          16 backed enums - no status is ever a magic string
├── DataTransferObjects/  DashboardFilter - one filter object for every panel
├── Models/         23 Eloquent models with their relationships
├── Policies/       MasterDataPolicy + one subclass per master entity
├── Repositories/   aggregate query objects (dashboard only)
├── Services/       business logic, one namespace per concern
│   ├── Delivery/   DeliveryStatusCalculator (pure) + DeliveryStatusService
│   ├── Performance/ delivery + supplier performance, service-rate strategies
│   ├── Dashboard/  dashboard payload, Pareto, critical materials
│   ├── MasterData/ shared CRUD flow + per-entity deletion guards
│   ├── Supplier/   period evaluation and scoring
│   ├── Setting/    cached KPI and system settings access
│   ├── Audit/      the append-only activity trail
│   └── Support/    document number allocation
├── Http/
│   ├── Controllers/
│   ├── Middleware/
│   └── Requests/
└── Exceptions/     BusinessRuleException

database/
├── migrations/     28 migrations with FKs, unique and composite indexes
├── factories/      22 factories
└── seeders/        reference data + a demo dataset that mirrors the design

resources/js/
├── Pages/          Inertia pages
├── Layouts/
├── Components/
├── Composables/
├── Stores/         Pinia - the dashboard filter selection
└── Types/          shared TypeScript contracts
```

## Delivery status model, in brief

Performance is measured at the **delivery line** grain. Each line records three
derived, indexed statuses so the dashboard aggregates in SQL rather than PHP:

```
timeliness_status   PENDING | ON_TIME | LATE
quantity_status     PENDING | SHORT | FULL | OVER
overall_status      PENDING | ON_TIME_FULL | LATE_FULL
                    | ON_TIME_SHORT | LATE_SHORT | OVER_DELIVERY
```

`DeliveryStatusService` is the only writer of those columns and of the
`purchase_order_items` receipt rollup. Full rules in
[docs/03-BUSINESS-RULES.md](docs/03-BUSINESS-RULES.md).

## Configuration that is not code

KPI targets, grade boundaries, the service-rate formula, the over-delivery
tolerance and the critical-material rules all live in the database
(`kpi_settings`, `system_settings`) and are editable at runtime. Nothing in the
Vue layer hard-codes a threshold.

## Development status

| Phase | Scope | Status |
|---|---|---|
| 1 | Setup, auth, schema, models, factories, seeders | **Complete** |
| 2 | Master data CRUD | **Complete** |
| 3 | Purchase Order module | Planned |
| 4 | Delivery & receiving | Planned |
| 5 | Dashboard | **Complete** |
| 6 | Problem management | Planned |
| 7 | Supplier performance | **Service layer complete**, UI pending |
| 8 | Reporting | Planned |
| 9 | Roles, policies, audit log | Planned |
| 10 | Caching & query optimisation | Planned |

The full roadmap with exit criteria is in
[docs/04-ROUTE-MAP.md](docs/04-ROUTE-MAP.md#7-development-roadmap).
