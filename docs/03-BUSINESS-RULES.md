# Business Rules

## 1. Status vocabulary (Enums)

| Enum | Values |
|---|---|
| `PurchaseOrderStatus` | DRAFT, SUBMITTED, APPROVED, PARTIAL, COMPLETED, CANCELLED |
| `DeliveryStatus` | PENDING, RECEIVED, PARTIAL, COMPLETED, CANCELLED |
| `TimelinessStatus` | PENDING, ON_TIME, LATE |
| `QuantityStatus` | PENDING, SHORT, FULL, OVER |
| `OverallDeliveryStatus` | PENDING, ON_TIME_FULL, LATE_FULL, ON_TIME_SHORT, LATE_SHORT, OVER_DELIVERY |
| `DeliveryItemCondition` | GOOD, DAMAGED, REJECTED, PARTIAL |
| `ProblemSeverity` | LOW, MEDIUM, HIGH, CRITICAL |
| `ProblemStatus` | OPEN, IN_PROGRESS, CLOSED, CANCELLED |
| `CorrectiveActionStatus` | OPEN, IN_PROGRESS, DONE |
| `SupplierGrade` | EXCELLENT, GOOD, AVERAGE, POOR |
| `SupplierType` | LOCAL, IMPORT, TOLLING, SERVICE |
| `RecordStatus` | ACTIVE, INACTIVE |
| `SupplierStatus` | ACTIVE, INACTIVE, BLACKLISTED |
| `UomType` | QTY, WEIGHT, VOLUME, LENGTH, AREA, TIME |

Magic strings are forbidden. Every status column casts to its enum on the model.

## 2. Quantity status (§13)

Given `ordered = purchase_order_items.qty_ordered` and
`received = SUM(delivery_items.qty_received)` over non-cancelled deliveries:

```
received == 0                          -> PENDING
received <  ordered                    -> SHORT
received == ordered                    -> FULL
received >  ordered                    -> OVER
```

With over-tolerance `t` (`system_settings.delivery.over_tolerance_percent`, default 0):
`received > ordered` is OVER only when `received > ordered * (1 + t/100)`, otherwise FULL.

## 3. Timeliness status (§13)

```
no receipt yet                                        -> PENDING
actual_delivery_date <= schedule_delivery_date        -> ON_TIME
actual_delivery_date >  schedule_delivery_date        -> LATE
```

`days_late = max(0, actual - schedule)` in whole days.

- **Delivery-line grain:** `actual` = the parent delivery's `delivery_date`.
- **PO-item grain:** `actual` = `last_receipt_date` (the receipt that settles the line).

## 4. Overall status matrix (§12, §40)

| Timeliness | Quantity | Overall |
|---|---|---|
| PENDING | PENDING | `PENDING` |
| ON_TIME | FULL | `ON_TIME_FULL` |
| LATE | FULL | `LATE_FULL` |
| ON_TIME | SHORT | `ON_TIME_SHORT` |
| LATE | SHORT | `LATE_SHORT` |
| any | OVER | `OVER_DELIVERY` |

Verified by `DeliveryCalculationTest` against the four §40 cases:

| PO Qty | Schedule | Actual | Received | Expected |
|---|---|---|---|---|
| 1000 | 2026-08-26 | 2026-08-26 | 1000 | ON_TIME_FULL |
| 1000 | 2026-08-26 | 2026-08-28 | 1000 | LATE_FULL |
| 1000 | 2026-08-26 | 2026-08-26 | 950 | ON_TIME_SHORT |
| 1000 | 2026-08-26 | 2026-08-28 | 950 | LATE_SHORT |

## 5. Metric definitions (§14–§16)

Population = all `delivery_items` whose parent delivery is **not** CANCELLED and whose
`delivery_date` falls inside the selected period, filtered by the active DashboardFilter.

```
total_delivery      = COUNT(delivery_items)
on_time_delivery    = COUNT(timeliness_status = ON_TIME)
late_delivery       = COUNT(timeliness_status = LATE)
short_delivery      = COUNT(quantity_status  = SHORT)

on_time_rate            = on_time_delivery / total_delivery * 100
quantity_fulfillment    = SUM(qty_received) / SUM(qty_ordered) * 100
```

### Service Rate strategies

`system_settings.service_rate.formula`:

- `on_time` **(default)** — `service_rate = on_time_rate`
- `weighted` — `service_rate = on_time_rate * w1 + quantity_fulfillment * w2`
  where `w1 = service_rate.weight_on_time`, `w2 = service_rate.weight_quantity`,
  normalised so `w1 + w2 = 1`.

Implemented as `ServiceRateStrategy` implementations resolved by
`ServiceRateCalculator`; adding a third formula requires no change to callers.

## 6. Supplier grading (§20)

Per supplier within the filtered period:

```
service_rate = on_time_delivery / total_delivery * 100
```

Grade thresholds are read from `kpi_settings`, never hard-coded:

```
service_rate >= GRADE_EXCELLENT (98) -> EXCELLENT
service_rate >= GRADE_GOOD      (95) -> GOOD
service_rate >= GRADE_AVERAGE   (90) -> AVERAGE
otherwise                            -> POOR
```

Ranking is `service_rate DESC, total_delivery DESC, supplier name ASC` — the tiebreakers
keep ordering deterministic for pagination and snapshot tests.

## 7. Pareto analysis (§18)

Problems grouped by `problem_categories`, ordered by count descending:

```
percentage      = count / total_problems * 100
cumulative_pct  = running SUM(percentage) in rank order
```

The 80% cut-off line marks the vital-few categories.

## 8. Critical material (§19)

A material is flagged critical inside a period when **any** enabled rule matches.
Rules are configurable via `system_settings.critical_material.*`:

| Rule key | Default | Condition |
|---|---|---|
| `flag_is_critical` | on | `materials.is_critical = true` and it has activity in period |
| `flag_late` | on | it has ≥1 delivery line with `timeliness_status = LATE` |
| `flag_short` | on | it has ≥1 PO item with `fulfillment_status = SHORT` |
| `flag_critical_problem` | on | it has ≥1 `delivery_problems` with `severity = CRITICAL` |

The KPI card counts **distinct materials** matching at least one enabled rule.

## 9. Purchase Order lifecycle

```
DRAFT --submit--> SUBMITTED --approve--> APPROVED --(receipts)--> PARTIAL --> COMPLETED
   \                   \                     \
    `------------------ cancel --------------'--> CANCELLED
```

- Items are editable only in DRAFT and SUBMITTED.
- Deliveries may only be created against an APPROVED or PARTIAL PO.
- After every receipt the PO recomputes: all items FULL/OVER → COMPLETED;
  some received → PARTIAL; none → stays APPROVED.
- COMPLETED and CANCELLED POs are immutable.
- POs are never physically deleted (§36).

## 10. Delivery lifecycle & recalculation

Creating or updating a delivery runs inside one database transaction:

1. Persist header and lines.
2. For every affected `purchase_order_item`, `DeliveryStatusService` recomputes
   in a deterministic order (`delivery_date`, then `delivery_items.id`):
   the cumulative quantity status of each of its delivery lines, then the item's
   own `qty_received`, `first/last_receipt_date` and three status columns.
3. Recompute the delivery header status from its lines.
4. Recompute the parent PO status.
5. Dispatch `DeliveryReceived` → listeners raise notifications and audit entries.

Over-receipt beyond tolerance is allowed but flagged OVER_DELIVERY — the business
wants visibility, not a hard block. Receiving against a CANCELLED PO is rejected
with a `BusinessRuleException`.

## 11. Problem & corrective action

- `problem_number` is generated `PRB-YYYYMM-####`, `delivery_number` `DN-YYYYMM-####`,
  `po_number` `PO-YYYYMM-####`, each allocated inside a locked transaction.
- Closing a problem requires at least one corrective action with status `DONE`.
- A problem's supplier comes from the receipt it was raised against, and a material it
  names must be one that receipt actually carried.
- `problem_date` is never in the future and never before the receipt's `delivery_date`.
- An empty `due_date` defaults to the severity's resolution window: LOW 30, MEDIUM 14,
  HIGH 7, CRITICAL 3 days.
- Recording the first corrective action moves the problem `OPEN -> IN_PROGRESS`.
  Completing one never closes the problem: closing carries `problem.close`.
- A completed corrective action is neither editable nor removable; a closure may rest on it.
- A settled problem (CLOSED or CANCELLED) is closed to everyone, super administrator
  included - `Gate::before` defers on the abilities listed in `AppServiceProvider::POLICY_ALONE`.
- Attachments go to the **private** disk (`storage/app/private/attachments`) under
  `problem-attachments/{problem ulid}/`, with a generated filename. The type is validated
  twice - extension in the form request, probed MIME in `AttachmentService` - and downloads
  stream through an authorized controller.
- Overdue problems (`due_date < today`, status not CLOSED/CANCELLED) are notified as one
  daily digest per recipient, sent to everyone holding `problem.close`.

## 12. Import rules (§26)

Upload → validate → preview → confirm → transactional import.
Unknown supplier/material/plant/warehouse/UOM codes are **errors**, never auto-created,
unless `system_settings.import.auto_create_master = true`. A single invalid row fails
its own row; the user chooses whether to import the valid remainder.
