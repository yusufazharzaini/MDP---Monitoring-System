# Demo Data

The seeders build a dataset that reproduces the reference dashboard from live
transactions. No figure on the dashboard is hard-coded anywhere in the frontend
or the backend — every one of them is an aggregate over `delivery_items`,
`purchase_order_items` and `delivery_problems`.

## 1. What the seed reproduces

Verified by `tests/Feature/Database/DemoDataIntegrityTest.php`.

### KPI cards — current month

| Metric | Reference | Seeded |
|---|---|---|
| Service Rate | 96.8% | **96.8%** |
| Total Delivery | 1,250 | **1,250** |
| On Time Delivery | 1,210 | **1,210** |
| Late Delivery | 40 | **40** |
| Short Delivery | 18 | **18** |
| Critical Material | 7 | **7** |

### Supplier ranking — current month

| Supplier | Reference rate | Seeded rate | Grade |
|---|---|---|---|
| Supplier A | 98.4% | **98.40%** | Excellent |
| Supplier B | 95.0% | **95.00%** | Good |
| Supplier C | 93.2% | **93.18%** | Average |
| Supplier D | 86.7% | **86.67%** | Poor |
| Supplier E | 93.3% | **93.33%** | Average |

### Service rate trend — six months

`97.2 → 96.5 → 98.1 → 95.8 → 97.0 → 96.8`, matching the reference line chart.

### Delivery status coverage — current month

All six `overall_status` values occur, so no branch of the status matrix is
undemonstrated:

| Status | Rows |
|---|---|
| ON_TIME_FULL | 1,194 |
| LATE_FULL | 34 |
| ON_TIME_SHORT | 12 |
| LATE_SHORT | 6 |
| OVER_DELIVERY | 4 |
| PENDING | 25 order lines awaiting receipt |

Plus **6 split shipments** in the period (16 across all six months): one order
line filled by two deliveries, the first cumulatively `SHORT` and the second
settling it as `FULL`. Partial and multiple delivery are therefore visible in the
data, not merely supported by the schema.

### Pareto — current month

| Category | Count | % | Cumulative |
|---|---|---|---|
| Late Delivery | 38 | 45.8% | 46% |
| Quantity Kurang | 24 | 28.9% | 75% |
| Material Salah | 12 | 14.5% | 89% |
| Dokumen Tidak Lengkap | 6 | 7.2% | 96% |
| Delivery Tidak Sesuai Schedule | 3 | 3.6% | 100% |

83 problems in total.

## 2. One reconciliation you should know about

**The reference mockup's own numbers do not add up.** The five named suppliers
imply 56 late lines:

```
A 250-246=4   B 180-171=9   C 220-205=15   D 150-130=20   E 120-112=8   = 56
```

but the KPI cards allow only **40** late lines across *all* suppliers. Both
cannot hold at once, so the seeder was built to satisfy the two things that are
independently checkable:

- the **headline KPI cards**, exactly; and
- the **five published service rates**, exactly.

The cost is per-supplier *volume*: Supplier C carries 220 lines (as published)
but Supplier D carries 30 rather than 150, because 150 lines at 86.67% would
alone consume half the month's late budget. Suppliers F, G and H carry the
remaining 450 lines with no late receipts, which is what the 96.8% aggregate
mathematically requires once A–E have taken 40 late lines between them.

This is documented rather than silently reconciled, and the allocation lives in
one place: `Database\Seeders\Support\DemoBlueprint::CURRENT_MONTH_ALLOCATION`.

## 2b. One invariant worth knowing

`CRITICAL` problem severity is a critical-material trigger, so the seeder only
assigns it to problems whose material already belongs to the problem set. Split
shipments are legitimately `SHORT` on ordinary materials, and without that guard
a `SHORT_DELIVERY` problem raised against one would quietly push the critical
material count from seven to eight. The guard is explicit in
`DemoProblemSeeder`, and `DemoDataIntegrityTest` fails if it is removed.

## 3. How the seven critical materials are produced

The critical-material rules (docs/03 §8) are configurable, so the count is an
emergent property, not a constant. The seed arranges the material sets so their
union is exactly seven:

```
MAT-0001 .. MAT-0005   carry every late and short receipt      (rules: late, short, critical problem)
MAT-0001, MAT-0002     also flagged materials.is_critical      (rule: is_critical)
MAT-0006, MAT-0007     flagged is_critical, always deliver clean (rule: is_critical)

union = {0001, 0002, 0003, 0004, 0005} ∪ {0001, 0002, 0006, 0007} = 7 materials
```

Turning a rule off in System Settings lowers the count, which is the point of
making the rules configurable.

## 4. Shape of the dataset

| Table | Rows |
|---|---|
| suppliers | 8 |
| plants / warehouses | 3 / 6 |
| materials | 20 |
| purchase_orders | 1,127 |
| purchase_order_items | 2,569 |
| deliveries | 1,118 |
| delivery_items | 2,560 |
| delivery_problems | 83 |
| corrective_actions | 83 |
| users | 7 |

The current month holds 1,250 delivery lines; the five preceding months hold
250 / 200 / 320 / 240 / 300. Twenty-five orders are scheduled but not yet
received, so the PO monitoring table has genuine PENDING rows.

**How the month's budget is spent.** Each month is planned against three
budgets — total delivery lines, late lines and short lines — and every kind of
line is accounted for against them:

| Line kind | Delivery lines | Late | Short |
|---|---|---|---|
| Split shipment (2 receipts) | 2 | 0 | 1 (the first receipt) |
| Late receipt | 1 | 1 | 0, or 1 when it is also short |
| Short-only receipt | 1 | 0 | 1 |
| Over-delivery | 1 | 0 | 0 |
| Clean receipt | 1 | 0 | 0 |

Because split shipments are punctual and over-deliveries satisfy their line,
neither disturbs a supplier's service rate — which is what lets the demo carry
realistic delivery patterns *and* reproduce the published figures exactly.

## 5. Why the seeder precomputes derived statuses

The seeders write `timeliness_status`, `quantity_status`, `overall_status` and
the `purchase_order_items` rollup directly, using the same
`DeliveryStatusCalculator` the runtime uses — bulk inserts instead of ~2,500
individual service calls.

That shortcut is only safe if it agrees with the real engine, so
`tests/Feature/Database/SeedConsistencyTest.php` re-runs `DeliveryStatusService`
over a sample of seeded lines and asserts **nothing changes**. If the rules and
the seeder ever drift apart, that test fails.

## 6. Demo accounts

Seeded by `UserSeeder`. The password comes from `MDP_DEMO_PASSWORD`
(default `password123` — change it before any shared deployment).

| Email | Role |
|---|---|
| superadmin@torica.test | SUPER_ADMIN |
| admin@torica.test | ADMIN |
| purchasing@torica.test | PURCHASING |
| warehouse@torica.test | WAREHOUSE |
| logistic@torica.test | LOGISTIC |
| management@torica.test | MANAGEMENT |
| viewer@torica.test | VIEWER |

## 7. Re-seeding

```bash
php artisan migrate:fresh --seed
```

The seed is anchored to the current month, so the dashboard always opens on a
period that has data — no matter when it is run.
