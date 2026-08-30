# Route Map, Services & Roadmap

## 1. Web routes (Inertia)

All routes are behind `auth` + `verified`; each carries a `permission:` middleware.

| Method | URI | Name | Permission |
|---|---|---|---|
| GET | `/` | `dashboard` | `dashboard.view` |
| GET | `/dashboard/data` | `dashboard.data` | `dashboard.view` |
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

| Service | Responsibility |
|---|---|
| `DeliveryStatusCalculator` | Pure functions: quantity, timeliness, overall status. No DB. |
| `DeliveryStatusService` | Persists recalculation across delivery lines, PO items, PO header |
| `PurchaseOrderService` | Create/update/submit/approve/cancel PO + items |
| `DeliveryService` | Create/update/cancel delivery + items, orchestrates recalculation |
| `ServiceRateCalculator` + strategies | Configurable service-rate formula |
| `DashboardService` | Assembles the §32 contract from `DashboardRepository` |
| `SupplierPerformanceService` | Ranking, scorecard, grade assignment |
| `SupplierEvaluationService` | Period evaluation generation and scoring |
| `ProblemService` | Problem lifecycle, closing rules |
| `CorrectiveActionService` | Corrective action lifecycle |
| `AttachmentService` | Private-disk storage, MIME validation, streaming |
| `CriticalMaterialService` | Configurable critical-material rules |
| `KpiSettingService` | Threshold lookup with caching |
| `SystemSettingService` | Typed settings access with caching |
| `AuditLogService` | Writes audit entries from observers/listeners |
| `ReportService` | Report datasets for Excel/PDF/print |
| `PurchaseOrderImportService` | Validate → preview → transactional import |
| `NumberGeneratorService` | Sequential PO/DN/PRB numbers under row lock |

## 5. Repository inventory

`DashboardRepository`, `SupplierPerformanceRepository`, `ReportRepository`.
Only these three — trivial CRUD stays in services (§3).

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
