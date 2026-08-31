# Route Map, Services & Roadmap

## 1. Web routes (Inertia)

All routes are behind `auth` + `verified`; each carries a `permission:` middleware.

| Method | URI | Name | Permission |
|---|---|---|---|
| GET | `/` | `dashboard` | `dashboard.view` |
| GET | `/dashboard/data` | `dashboard.data` | `dashboard.view` — JSON, used by the filter bar and refresh button |
| GET | `/workspace` | `home` | authenticated — permission-filtered module launcher |
| GET | `/suppliers` | `suppliers.index` | `supplier.view` |
| GET | `/suppliers/create` | `suppliers.create` | `supplier.create` |
| POST | `/suppliers` | `suppliers.store` | `supplier.create` |
| GET | `/suppliers/{supplier}` | `suppliers.show` | `supplier.view` |
| GET | `/suppliers/{supplier}/edit` | `suppliers.edit` | `supplier.update` |
| PUT | `/suppliers/{supplier}` | `suppliers.update` | `supplier.update` |
| DELETE | `/suppliers/{supplier}` | `suppliers.destroy` | `supplier.delete` |
| resource | `/supplier-contacts` | `supplier-contacts.*` | `supplier.*` |
| resource | `/plants` | `plants.*` | `plant.*` |
| resource | `/warehouses` | `warehouses.*` | `warehouse.*` |
| resource | `/materials` | `materials.*` | `material.*` |
| resource | `/material-categories` | `material-categories.*` | `material.*` |
| resource | `/uoms` | `uoms.*` | `material.*` |
| resource | `/departments` | `departments.*` | `user.*` |
| resource | `/purchase-orders` | `purchase-orders.*` | `po.*` |
| POST | `/purchase-orders/{po}/submit` | `purchase-orders.submit` | `po.update` |
| POST | `/purchase-orders/{po}/approve` | `purchase-orders.approve` | `po.approve` |
| POST | `/purchase-orders/{po}/cancel` | `purchase-orders.cancel` | `po.cancel` |
| GET | `/purchase-orders/import` | `purchase-orders.import.form` | `po.create` |
| POST | `/purchase-orders/import/preview` | `purchase-orders.import.preview` | `po.create` |
| POST | `/purchase-orders/import/confirm` | `purchase-orders.import.confirm` | `po.create` |
| resource | `/deliveries` | `deliveries.*` | `delivery.*` |
| POST | `/deliveries/{delivery}/cancel` | `deliveries.cancel` | `delivery.cancel` |
| GET | `/problems` | `problems.index` | `problem.view` |
| GET | `/problems/{problem}` | `problems.show` | `problem.view` |
| GET | `/problems/{problem}/edit` | `problems.edit` | `problem.update` |
| PUT | `/problems/{problem}` | `problems.update` | `problem.update` |
| POST | `/problems/{problem}/close` | `problems.close` | `problem.close` |
| POST | `/problems/{problem}/cancel` | `problems.cancel` | `problem.close` |
| GET | `/deliveries/{delivery}/problems/create` | `problems.create` | `problem.create` |
| POST | `/deliveries/{delivery}/problems` | `problems.store` | `problem.create` |
| POST | `/problems/{problem}/corrective-actions` | `corrective-actions.store` | `problem.update` |
| PUT | `/problems/{problem}/corrective-actions/{action}` | `corrective-actions.update` | `problem.update` |
| POST | `/problems/{problem}/corrective-actions/{action}/start` | `corrective-actions.start` | `problem.update` |
| POST | `/problems/{problem}/corrective-actions/{action}/complete` | `corrective-actions.complete` | `problem.update` |
| DELETE | `/problems/{problem}/corrective-actions/{action}` | `corrective-actions.destroy` | `problem.update` |
| POST | `/problems/{problem}/attachments` | `problem-attachments.store` | `problem.update` |
| GET | `/problems/{problem}/attachments/{attachment}` | `problem-attachments.download` | `problem.view` |
| DELETE | `/problems/{problem}/attachments/{attachment}` | `problem-attachments.destroy` | `problem.update` |
| resource | `/problem-categories` | `problem-categories.*` | `problem.*` |
| GET | `/supplier-performance` | `supplier-performance.index` | `report.view` |
| GET | `/supplier-performance/{supplier}` | `supplier-performance.show` | `report.view` |
| GET | `/critical-materials` | `critical-materials.index` | `report.view` |
| GET | `/supplier-evaluations` | `supplier-evaluations.index` | `evaluation.view` |
| POST | `/supplier-evaluations` | `supplier-evaluations.store` | `evaluation.create` |
| GET | `/supplier-evaluations/{evaluation}` | `supplier-evaluations.show` | `evaluation.view` |
| POST | `/supplier-evaluations/{evaluation}/approve` | `supplier-evaluations.approve` | `evaluation.approve` |
| POST | `/supplier-evaluations/{evaluation}/reopen` | `supplier-evaluations.reopen` | `evaluation.approve` |
| GET | `/reports` | `reports.index` | `report.view` |
| GET | `/reports/{type}/export` | `reports.export` | `report.export` |
| resource | `/users` | `users.*` | `user.*` |
| resource | `/roles` | `roles.*` | `user.*` |
| GET | `/notifications` | `notifications.index` | authenticated |
| POST | `/notifications/{id}/read` | `notifications.read` | authenticated |
| GET | `/audit-logs` | `audit-logs.index` | `audit.view` |
| GET | `/settings` | `settings.index` | `setting.view` |
| PUT | `/settings` | `settings.update` | `setting.update` |
| resource | `/kpi-settings` | `kpi-settings.*` | `setting.*` |

## 2. Dashboard JSON contract (§32)

```json
{
  "filters": { "period": "2026-06", "date_from": "...", "date_to": "...",
               "plant_id": null, "supplier_id": null, "material_id": null,
               "material_category_id": null, "status": null },
  "summary": { "service_rate": 96.8, "total_delivery": 1250, "on_time_delivery": 1210,
               "late_delivery": 40, "short_delivery": 18, "critical_material": 7,
               "quantity_fulfillment": 99.1, "target": 95.0, "target_met": true },
  "trend": [ { "period": "2026-01", "label": "Jan", "service_rate": 97.2,
               "total_delivery": 210, "on_time_delivery": 204, "target": 95.0 } ],
  "supplier_performance": [ { "rank": 1, "supplier_id": 1, "supplier_name": "Supplier A",
               "total_delivery": 250, "on_time_delivery": 246,
               "service_rate": 98.4, "grade": "EXCELLENT" } ],
  "pareto": [ { "category": "Late Delivery", "count": 38, "percentage": 46.3,
               "cumulative_percentage": 46.3 } ],
  "recent_deliveries": [ { "po_number": "PO-202606-0001", "supplier": "Supplier A",
               "material": "Resin PP", "schedule_delivery_date": "2026-06-25",
               "actual_delivery_date": "2026-06-25", "qty_ordered": 500,
               "qty_received": 500, "overall_status": "ON_TIME_FULL", "remarks": "-" } ]
}
```

## 3. Enum inventory

`App\Enums`: `PurchaseOrderStatus`, `DeliveryStatus`, `TimelinessStatus`, `QuantityStatus`,
`OverallDeliveryStatus`, `DeliveryItemCondition`, `ProblemSeverity`, `ProblemStatus`,
`CorrectiveActionStatus`, `SupplierGrade`, `SupplierType`, `SupplierStatus`, `RecordStatus`,
`UomType`, `AuditAction`, `SettingType`.

Each exposes `label()` and `badgeVariant()` (success / danger / warning / info / neutral)
so the UI never maps status→colour by hand.

## 4. Service inventory

### Built

| Service | Namespace | Responsibility |
|---|---|---|
| `DeliveryStatusCalculator` | `Services\Delivery` | Pure functions: quantity, timeliness, overall status. No DB. |
| `DeliveryStatusService` | `Services\Delivery` | Persists recalculation across delivery lines, PO items, PO header |
| `DeliveryPerformanceService` | `Services\Performance` | Per-record rules + aggregate rates over a filter |
| `SupplierPerformanceService` | `Services\Performance` | Ranking, monthly trend, scorecard, grading |
| `ServiceRateCalculator` + 2 strategies | `Services\Performance` | Configurable service-rate formula |
| `DashboardService` | `Services\Dashboard` | Assembles the §32 contract |
| `ParetoAnalysisService` | `Services\Dashboard` | Frequency, share, cumulative curve, vital few |
| `CriticalMaterialService` | `Services\Dashboard` | Four configurable rules, risk banding |
| `KpiSettingService` | `Services\Setting` | Threshold lookup, cached as plain arrays |
| `SystemSettingService` | `Services\Setting` | Typed settings access with caching |
| `SupplierEvaluationService` | `Services\Supplier` | Scorecard generation, sign-off and reopening |
| `AuditLogService` | `Services\Audit` | Writes audit entries, diffing only what changed |
| `NumberGeneratorService` | `Services\Support` | Sequential PO/DN/PRB numbers under row lock |
| `ProblemService` | `Services\Problem` | The problem lifecycle, and the rule that closing needs a completed action |
| `CorrectiveActionService` | `Services\Problem` | Recording, starting, completing and withdrawing actions |
| `AttachmentService` | `Services\Problem` | Private-disk storage, MIME validation, authorised streaming |

| `MasterDataService` + 8 subclasses | `Services\MasterData` | Shared create/update/retire flow, per-entity deletion guards |
| `PurchaseOrderService` | `Services\PurchaseOrder` | The PO lifecycle: create, amend, submit, approve, cancel |
| `DeliveryService` | `Services\Delivery` | Booking, correcting and reversing goods receipts |
| `SyncDeliveryItems` | `Actions\Delivery` | Reconciles receipt lines against the order they belong to |
| `SyncPurchaseOrderItems` | `Actions\PurchaseOrder` | Reconciles order lines, protecting received ones |
| `RecalculatePurchaseOrderTotal` | `Actions\PurchaseOrder` | Sole writer of the denormalised order total |

### Planned

`ReportService`, `PurchaseOrderImportService`.

## 4.0 Purchase order lifecycle (Phase 3)

```
DRAFT --submit--> SUBMITTED --approve--> APPROVED --(receipts)--> PARTIAL --> COMPLETED
   \                   \                     \
    `----------------- cancel ----------------'--> CANCELLED
```

Guarded in `PurchaseOrderService`, so the same rules apply however a transition
is reached - screen, console command, or a future import.

| Rule | Why |
|---|---|
| A new order is always a DRAFT and always gets a number | A saved order without an identity cannot be referred to |
| Only DRAFT and SUBMITTED may be edited | Once approved, the supplier is already working to those lines |
| Submitting requires at least one line | An order with nothing on it is not an order |
| Only a SUBMITTED order may be approved | Skipping submission skips the review |
| The creator may not approve their own order | Standard segregation of duties; switchable via `purchase_order.require_separate_approver` |
| A line with receipts cannot be removed | It carries a receipt history and a rollup |
| A line's quantity cannot fall below what arrived | It would leave the order over-delivered on paper |
| Cancelling requires a reason | A cancellation nobody can explain later is an audit gap |
| COMPLETED and CANCELLED are terminal | History, not a workspace |
| An order is never deleted, by anyone | Structural: the policy denies it and the super-admin gate defers to that denial |

Line numbers are re-sequenced on every save. Because `(purchase_order_id, line_no)`
is unique, existing and new lines are first parked in two separate temporary
ranges and only then brought down to 1..n - renumbering in place would collide
the moment one line took a number another still held.

Events: `PurchaseOrderSubmitted`, `PurchaseOrderApproved`, `PurchaseOrderCancelled`,
all implementing `PurchaseOrderLifecycleEvent`. Listeners bind to that *interface*
rather than a base class, because Laravel's dispatcher resolves listeners through
interfaces - so a future transition needs a new event, not a new registration.

## 4.0.1 Receiving (Phase 4)

`DeliveryService` owns the transaction; `DeliveryStatusService` owns the
consequences. Every write ends by asking that service to settle the derived
statuses, the order-line rollup and the purchase order's own status - so a
receipt and the numbers it moves are never saved apart.

| Rule | Why |
|---|---|
| Goods may only be booked against an APPROVED or PARTIAL order | A receipt without a live commitment behind it is not measurable |
| A receipt's supplier and plant come from the order, not the form | A receipt cannot re-attribute itself elsewhere |
| A line's material and unit come from the order line | A receipt records what was ordered arriving, not something else under its number |
| Every line must belong to the receipt's own order | No booking goods against someone else's commitment |
| One receipt records an order line at most once | The KPI grain depends on it; the unique key enforces it |
| The delivery date cannot be in the future | It would count as on time against a schedule it has not had to meet |
| Receiving more than ordered is flagged, not blocked | The business needs to see over-delivery, not be prevented from recording it |
| REJECTED goods are recorded but never fulfil the order | Rejected material did not arrive in any useful sense |
| Cancelling keeps the lines but clears their verdicts | What was booked and later reversed is itself history |
| A receipt is never deleted, by anyone | Same structural rule as purchase orders |

Cancelling a receipt recalculates every order line it touched, so the purchase
order falls back from COMPLETED to PARTIAL - or to APPROVED - as the remaining
receipts warrant.

Events: `DeliveryReceived`, `DeliveryUpdated`, `DeliveryCancelled`, all behind
`DeliveryLifecycleEvent`. They fire *after* the statuses settle, so a listener
always reads a consistent picture.

## 4.0.2 Problem management (Phase 6)

A problem is raised against a goods receipt, which is what gives it a supplier
and a period. `ProblemService` owns `problem_number`, `status` and `closed_at`;
none of them are fillable, because each carries a rule no form can enforce.

Lifecycle: `OPEN -> IN_PROGRESS -> CLOSED`, with `CANCELLED` as the exit for a
report that should never have been raised.

| Rule | Why |
|---|---|
| Closing requires at least one corrective action with status DONE | "Resolved" must have evidence behind it, not just an opinion |
| A problem's supplier comes from the receipt, not the form | A problem cannot re-attribute itself and distort another supplier's score |
| A named material must be one the receipt actually carried | Otherwise Pareto and the critical-material rule count a material against a delivery that never held it |
| A problem cannot be dated in the future, or before its receipt | It is observed when the goods are handled |
| An empty due date defaults to the severity's resolution window | LOW 30 / MEDIUM 14 / HIGH 7 / CRITICAL 3 days - it is what makes "overdue" mean anything |
| No problem may be raised against a cancelled receipt | The receipt was reversed; there is nothing left to report against |
| Recording the first corrective action moves the problem to IN_PROGRESS | Status should follow what is happening, not wait to be remembered |
| Completing an action never closes the problem | Closing is a separate decision carrying `problem.close` |
| A completed action can be neither edited nor removed | A closure may rest on it |
| A settled problem is closed to everyone, super administrator included | See the gate note below |
| A problem is never deleted - it is cancelled | Same structural rule as orders and receipts |

Reporting (`problem.create`) and closing (`problem.close`) are separate rights on
purpose: WAREHOUSE and PURCHASING raise and work problems, LOGISTIC and
MANAGEMENT sign them off.

**The super-admin bypass defers on state.** `Gate::before` grants a super
administrator every permission, but a closed problem is not a permission
question - it is the record's own state. `AppServiceProvider::POLICY_ALONE`
lists the abilities the policy decides alone, so the UI never offers a button
the service would then refuse.

**Attachments.** Bytes go to the `private` disk, which has no public URL, under
`problem-attachments/{problem ulid}/{generated ulid}.{ext}`. Nothing the
uploader controls reaches a path - the original filename is kept for display
only - so a name like `../../.env` cannot escape the directory. The type is
checked twice, because either check alone is bypassable: the form request
validates the extension against the guessed type, and `AttachmentService`
validates the probed MIME type against its own allow-list. Downloads stream
through `ProblemAttachmentController`, which runs the policy first; `file_path`
is `$hidden` on the model so it never reaches a payload.

**Notifications.** `problems:notify-overdue`, scheduled daily at 07:00, sends
one `OverdueProblemsDigest` per recipient rather than one per problem - a
supervisor with thirty overdue problems needs a queue to work through, not
thirty mails. Recipients are the users holding `problem.close`. It counts in the
database and lists only the ten worst, so its cost does not grow with the
backlog.

Events: `ProblemReported`, `ProblemUpdated`, `ProblemClosed`, `ProblemCancelled`,
all behind `ProblemLifecycleEvent`, audited by one listener.

## 4.0.3 Supplier performance and evaluation (Phase 7)

The ranking, the scorecard and the critical-material list are read models over
aggregates that already existed; the new business rule in this phase is the
evaluation lifecycle.

**Ranking order is fixed, not incidental.** Service rate descending, then
delivery count, then supplier name. The tiebreakers are the point: two suppliers
on 100% are not equally proven, so the one with more deliveries ranks higher,
and name settles the rest so the table does not reshuffle between page loads.

**A supplier that did not deliver is absent, not ranked at 0%.** Nothing to
measure is a different answer from measured badly, and a 0% row would drag any
average that reads the table.

**Grade bands come from `kpi_settings`.** The screen renders the bands it is
given, floor and ceiling, so retuning a threshold moves the legend and the
grades together. Nothing about 98 / 95 / 90 is written in the page.

### The evaluation lifecycle

| Rule | Why |
|---|---|
| A generated scorecard starts as `DRAFT` | It is a working figure until somebody signs it |
| A DRAFT is recomputed from transactions as often as asked | That is what makes it current |
| Approving freezes it, recording who and when | It becomes a record of what was approved |
| An APPROVED scorecard is never silently recomputed | A correction months later must not restate a figure a manager put their name to |
| Reopening returns it to DRAFT and requires a reason | Moving published figures is a decision the trail has to explain |
| A month-end batch skips approved scorecards rather than failing | One signed-off supplier must not stop the rest of the run |
| A supplier with no receipts in the month is not evaluated | Delivery, quantity and quality would all be zero for want of data while responsiveness scored full marks, because no problem can be raised against a delivery that never happened - 10/100 and grade POOR reads as terrible performance when it means no activity |
| A scorecard is never deleted | Management history |

Approving is its own permission. `report.view` reaches every VIEWER, and a
viewer must not sign off a supplier's grade - hence `evaluation.view`,
`evaluation.create` and `evaluation.approve`, held by MANAGEMENT in full and by
PURCHASING as far as generating.

`AppServiceProvider::POLICY_ALONE` covers `regenerate`, `approve` and `reopen`,
so the super-admin bypass defers to the record's state here as it does for
settled purchase orders, receipts and problems.

Events: `SupplierEvaluationApproved`, `SupplierEvaluationReopened`, both behind
`EvaluationLifecycleEvent`, audited by one listener.

## 4.1 Master-data deletion guards (Phase 2)

Master data soft-deletes, so retiring a record never destroys history. What the
guards protect is the present - a record still needed by work in flight cannot
be taken away underneath it. A refused delete raises `BusinessRuleException`,
which renders as a flashed error naming what is still using the record.

| Entity | Refused while |
|---|---|
| Supplier | it has a purchase order that is not yet completed or cancelled |
| Plant | it has warehouses, or an outstanding purchase order |
| Warehouse | an outstanding order line still delivers into it |
| Material | it sits on an outstanding order line |
| Material category | any material still belongs to it |
| UOM | a material or an order line still measures in it |
| Department | staff are still assigned to it |
| Supplier contact | never - a contact is a detail of its supplier |

## 5. Repository inventory

| Repository | Responsibility |
|---|---|
| `DeliveryAggregateQuery` | The three filtered base populations: delivery lines, order lines, problems |
| `DashboardRepository` | Every dashboard aggregate, expressed as SQL |

`ReportRepository` follows in Phase 8. Trivial CRUD stays in services (§3).

## 5.1 Aggregation, and why it matters here

Every figure on the dashboard is a `GROUP BY`, not a `foreach`. A month of
1,250 delivery lines crosses into PHP as a handful of integers.

| Panel | Queries |
|---|---|
| KPI cards | 2 (counts at delivery-line grain, quantities at order-line grain) |
| Six-month trend | 2 — one grouped query per grain, **not one per month** |
| Supplier ranking | 1 grouped query, whatever the number of suppliers |
| Pareto | 1 grouped query |
| PO monitoring | 1 |
| Critical materials | 4 rule queries + 1 hydration |
| **Whole payload** | **≤ 14, independent of how many deliveries the month holds** |

`DeliveryPerformanceServiceTest`, `SupplierPerformanceServiceTest`,
`ParetoAnalysisServiceTest` and `DashboardServiceTest` assert these counts, so a
`foreach` creeping into an aggregate path fails the build.

Two grains coexist deliberately, because the specification defines them that
way: **counts** are delivery lines dated by arrival; **quantities** are order
lines dated by promise. `DeliveryMetrics` carries both and derives every rate
from them, which is what stops two panels quoting different denominators.

## 6. Vue component inventory

**Layout:** `AppLayout`, `AuthLayout`, `Sidebar`, `Topbar`, `FlashToasts`
**UI:** `DashboardCard`, `DataTable`, `FilterBar`, `DateRangePicker`, `SupplierSelect`,
`PlantSelect`, `MaterialSelect`, `StatusBadge`, `Modal`, `ConfirmDialog`, `Pagination`,
`EmptyState`, `LoadingState`, `ErrorState`, `FormField`, `PageHeader`
**Charts:** `ServiceRateChart`, `SupplierPerformanceChart`, `ParetoChart`, `BaseChart`

## 7. Development roadmap

| Phase | Scope | Exit criteria |
|---|---|---|
| 1 | Setup, auth, migrations, enums, models, factories, seeders | `migrate:fresh --seed` clean, Phase-1 tests green ✅ |
| 2 | Master data CRUD (supplier, contact, plant, warehouse, material, category, UOM, department) | CRUD + policies + feature tests ✅ |
| 3 | Purchase Order + items, submit/approve/cancel | PO lifecycle tests green ✅ |
| 4 | Delivery + items + automatic status calculation | §40 business-rule tests green ✅ |
| 5 | Dashboard: KPI, trend, ranking, pareto, PO monitoring | §32 contract test green ✅ |
| **6** | Problem management, attachments, corrective action | Problem lifecycle tests green ✅ |
| **7** | Supplier performance, scorecard, evaluation | Ranking tests green ✅ |
| **8** | Reporting: Excel, PDF, print | Export tests green ← next |
| 9 | Roles, permissions, policies, audit log | Permission tests green |
| 10 | Caching, indexes, query tuning, queue, notifications | No N+1; dashboard aggregate-only |

Each phase must run without error and must not break the previous phase.
