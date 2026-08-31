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
| resource | `/problems` | `problems.*` | `problem.*` |
| POST | `/problems/{problem}/close` | `problems.close` | `problem.close` |
| POST | `/problems/{problem}/attachments` | `problems.attachments.store` | `problem.update` |
| GET | `/problems/attachments/{attachment}` | `problems.attachments.download` | `problem.view` |
| DELETE | `/problems/attachments/{attachment}` | `problems.attachments.destroy` | `problem.update` |
| resource | `/problems/{problem}/corrective-actions` | `corrective-actions.*` | `problem.update` |
| resource | `/problem-categories` | `problem-categories.*` | `problem.*` |
| GET | `/supplier-performance` | `supplier-performance.index` | `report.view` |
| GET | `/supplier-performance/{supplier}` | `supplier-performance.show` | `report.view` |
| resource | `/supplier-evaluations` | `supplier-evaluations.*` | `report.view` |
| GET | `/critical-materials` | `critical-materials.index` | `dashboard.view` |
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
| `SupplierEvaluationService` | `Services\Supplier` | Period scorecard generation and grading |
| `KpiSettingService` | `Services\Setting` | Threshold lookup, cached as plain arrays |
| `SystemSettingService` | `Services\Setting` | Typed settings access with caching |
| `AuditLogService` | `Services\Audit` | Writes audit entries, diffing only what changed |
| `NumberGeneratorService` | `Services\Support` | Sequential PO/DN/PRB numbers under row lock |

| `MasterDataService` + 8 subclasses | `Services\MasterData` | Shared create/update/retire flow, per-entity deletion guards |
| `PurchaseOrderService` | `Services\PurchaseOrder` | The PO lifecycle: create, amend, submit, approve, cancel |
| `SyncPurchaseOrderItems` | `Actions\PurchaseOrder` | Reconciles order lines, protecting received ones |
| `RecalculatePurchaseOrderTotal` | `Actions\PurchaseOrder` | Sole writer of the denormalised order total |

### Planned

`DeliveryService`, `ProblemService`,
`CorrectiveActionService`, `AttachmentService`, `ReportService`,
`PurchaseOrderImportService`.

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
| **1** | Setup, auth, migrations, enums, models, factories, seeders | `migrate:fresh --seed` clean, Phase-1 tests green |
| 2 | Master data CRUD (supplier, contact, plant, warehouse, material, category, UOM, department) | CRUD + policies + feature tests |
| 3 | Purchase Order + items, submit/approve/cancel | PO lifecycle tests green |
| 4 | Delivery + items + automatic status calculation | §40 business-rule tests green |
| 5 | Dashboard: KPI, trend, ranking, pareto, PO monitoring | §32 contract test green |
| 6 | Problem management, attachments, corrective action | Problem lifecycle tests green |
| 7 | Supplier performance, scorecard, evaluation | Ranking tests green |
| 8 | Reporting: Excel, PDF, print | Export tests green |
| 9 | Roles, permissions, policies, audit log | Permission tests green |
| 10 | Caching, indexes, query tuning, queue, notifications | No N+1; dashboard aggregate-only |

Each phase must run without error and must not break the previous phase.
