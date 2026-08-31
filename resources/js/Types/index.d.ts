/**
 * Shared Inertia page props and domain types.
 */

export type BadgeVariant = 'success' | 'danger' | 'warning' | 'info' | 'neutral';

export interface AuthUser {
    id: string;
    name: string;
    email: string;
    position: string | null;
    roles: string[];
    permissions: string[];
}

export interface KpiThreshold {
    name: string;
    target: number;
    warning: number | null;
    critical: number | null;
    unit: string;
}

export interface FlashMessages {
    success: string | null;
    error: string | null;
    warning: string | null;
}

export interface SharedPageProps {
    auth: { user: AuthUser | null };
    kpi: Record<string, KpiThreshold>;
    flash: FlashMessages;
    app: { name: string };
    errors: Record<string, string>;
    [key: string]: unknown;
}

/** A Laravel length-aware paginator, as serialised by Inertia. */
export interface Paginated<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: Array<{ url: string | null; label: string; active: boolean }>;
}

export interface SelectOption {
    value: string | number;
    label: string;
    variant?: BadgeVariant;
}

/* ---------------------------------------------------------------------------
 * Dashboard contract - mirrors the payload assembled by DashboardService.
 * Every figure here is computed server-side; Vue formats but never calculates.
 * ------------------------------------------------------------------------ */

export interface DashboardFilters {
    period: string;
    date_from: string;
    date_to: string;
    plant_id: number | null;
    supplier_id: number | null;
    material_id: number | null;
    material_category_id: number | null;
    status: string | null;
}

export interface DashboardSummary {
    service_rate: number;
    total_delivery: number;
    on_time_delivery: number;
    late_delivery: number;
    short_delivery: number;
    over_delivery: number;
    critical_material: number;
    pending_order_lines: number;
    quantity_ordered: number;
    quantity_received: number;
    quantity_shortage: number;
    quantity_excess: number;
    on_time_rate: number;
    late_rate: number;
    quantity_fulfillment: number;
    target: number;
    target_met: boolean;
    severity: 'success' | 'warning' | 'critical' | 'info';
}

export interface TrendPoint {
    period: string;
    label: string;
    total_delivery: number;
    on_time_delivery: number;
    late_delivery: number;
    service_rate: number | null;
    target: number;
}

export interface SupplierPerformanceRow {
    rank: number;
    supplier_id: number;
    supplier_ulid: string;
    supplier_code: string;
    supplier_name: string;
    total_delivery: number;
    on_time_delivery: number;
    late_delivery: number;
    short_delivery: number;
    service_rate: number;
    grade: string;
    grade_label: string;
    grade_variant: BadgeVariant;
}

export interface ParetoCategory {
    rank: number;
    category_id: number;
    category_code: string;
    category: string;
    count: number;
    percentage: number;
    cumulative_percentage: number;
    is_vital_few: boolean;
}

export interface ParetoDataset {
    threshold: number;
    total_problems: number;
    vital_few_count: number;
    categories: ParetoCategory[];
}

export interface MonitoringRow {
    no: number;
    purchase_order_ulid: string;
    po_number: string;
    supplier: string;
    material: string;
    material_code: string;
    schedule_delivery_date: string;
    actual_delivery_date: string | null;
    qty_ordered: number;
    qty_received: number;
    overall_status: string;
    status_label: string;
    status_variant: BadgeVariant;
    remarks: string;
}

export interface CriticalMaterialRow {
    material_id: number;
    material_ulid: string;
    material_code: string;
    material_name: string;
    category: string;
    uom: string;
    is_flagged_critical: boolean;
    late_count: number;
    short_count: number;
    shortage_quantity: number;
    critical_problem_count: number;
    reasons: string[];
    risk_level: string;
    risk_label: string;
    risk_variant: BadgeVariant;
    risk_score: number;
}

export interface DashboardDefinition {
    title: string;
    description: string;
    formula: string;
}

export interface DashboardPayload {
    filters: DashboardFilters;
    summary: DashboardSummary;
    trend: TrendPoint[];
    supplier_performance: SupplierPerformanceRow[];
    pareto: ParetoDataset;
    recent_deliveries: MonitoringRow[];
    critical_materials: CriticalMaterialRow[];
    definitions: DashboardDefinition[];
}

export interface DashboardFilterOptions {
    plants: SelectOption[];
    suppliers: SelectOption[];
    materials: SelectOption[];
    materialCategories: SelectOption[];
}

/* --- Purchase order ----------------------------------------------------- */

export interface PurchaseOrderLine {
    id: number | null;
    line_no?: number;
    material_id: number | null;
    material_code?: string | null;
    material_name?: string | null;
    warehouse_id: number | null;
    warehouse_name?: string | null;
    uom_id: number | null;
    uom_code?: string | null;
    schedule_delivery_date: string;
    qty_ordered: number;
    qty_received?: number;
    outstanding?: number;
    unit_price: number;
    amount?: number;
    overall_status?: string;
    overall_status_label?: string;
    overall_status_variant?: BadgeVariant;
    remarks: string | null;
}

export interface PurchaseOrderRecord {
    id: number;
    ulid: string;
    po_number: string;
    po_date: string;
    supplier_id: number;
    supplier_name: string | null;
    supplier_code: string | null;
    plant_id: number;
    plant_name: string | null;
    currency: string;
    payment_term: string | null;
    remarks: string | null;
    items_count: number;
    total_amount: number;
    status: string;
    status_label: string;
    status_variant: BadgeVariant;
    created_by_name?: string | null;
    approved_by_name?: string | null;
    approved_at?: string | null;
    items: PurchaseOrderLine[];
    deliveries: Array<{
        ulid: string;
        delivery_number: string;
        delivery_date: string;
        status: string;
        status_label: string;
        status_variant: BadgeVariant;
    }>;
}

export interface PurchaseOrderFormOptions {
    suppliers: SelectOption[];
    plants: SelectOption[];
    materials: Array<SelectOption & { uom_id: number }>;
    uoms: SelectOption[];
    warehouses: Array<SelectOption & { plant_id: number }>;
}

/* --- Delivery ----------------------------------------------------------- */

export interface ReceivableLine {
    purchase_order_item_id: number;
    line_no: number;
    material_code: string | null;
    material_name: string | null;
    warehouse_name: string | null;
    uom_code: string | null;
    schedule_delivery_date: string;
    qty_ordered: number;
    qty_received: number;
    outstanding: number;
    booked_here: number | null;
    booked_condition: string | null;
}

export interface ReceivingContext {
    id: number;
    ulid: string;
    po_number: string;
    po_date: string;
    supplier_name: string | null;
    plant_name: string | null;
    status: string;
    status_label: string;
    status_variant: BadgeVariant;
    lines: ReceivableLine[];
}

export interface DeliveryLine {
    id: number;
    purchase_order_item_id: number;
    line_no: number | null;
    material_code: string | null;
    material_name: string | null;
    uom_code: string | null;
    schedule_delivery_date: string | null;
    qty_ordered: number;
    qty_received: number;
    condition: string;
    condition_label: string;
    condition_variant: BadgeVariant;
    overall_status: string;
    overall_status_label: string;
    overall_status_variant: BadgeVariant;
    days_late: number;
    remarks: string | null;
}

export interface DeliveryRecord {
    id: number;
    ulid: string;
    delivery_number: string;
    delivery_date: string;
    do_number: string | null;
    vehicle_number: string | null;
    driver_name: string | null;
    po_number: string | null;
    purchase_order_ulid: string | null;
    supplier_name: string | null;
    plant_name: string | null;
    received_by_name: string | null;
    remarks: string | null;
    items_count: number;
    problems_count: number;
    status: string;
    status_label: string;
    status_variant: BadgeVariant;
    items: DeliveryLine[];
}

/* --- Delivery problem --------------------------------------------------- */

export interface ProblemAttachmentRow {
    ulid: string;
    file_name: string;
    human_file_size: string;
    mime_type: string;
    is_image: boolean;
    uploaded_by: string | null;
    uploaded_at: string | null;
}

export interface CorrectiveActionRow {
    id: number;
    action_date: string | null;
    due_date: string | null;
    description: string;
    action_by: string | null;
    status: string;
    status_label: string;
    status_variant: BadgeVariant;
    is_done: boolean;
    is_overdue: boolean;
    completed_at: string | null;
}

export interface ProblemSummary {
    id: number;
    ulid: string;
    problem_number: string;
    problem_date: string | null;
    due_date: string | null;
    supplier_name: string | null;
    material_name: string | null;
    category_name: string | null;
    delivery_number: string | null;
    delivery_ulid: string | null;
    pic: string | null;
    severity: string;
    severity_label: string;
    severity_variant: BadgeVariant;
    status: string;
    status_label: string;
    status_variant: BadgeVariant;
    /** Both computed server-side: the page never derives "late" from dates. */
    is_overdue: boolean;
    days_until_due: number | null;
    attachments_count: number;
    corrective_actions_count: number;
}

export interface ProblemRecord extends ProblemSummary {
    description: string;
    root_cause: string | null;
    closed_at: string | null;
    reported_by: string | null;
    plant_name: string | null;
    po_number: string | null;
    po_ulid: string | null;
    delivery_date: string | null;
    attachments: ProblemAttachmentRow[];
    corrective_actions: CorrectiveActionRow[];
}

/** The subset the edit form reads back; the rest of ProblemSummary comes along. */
export interface ProblemFormRecord extends ProblemSummary {
    material_id: number | null;
    problem_category_id: number;
    description: string;
    root_cause: string | null;
}

export interface ProblemDeliveryContext {
    ulid: string;
    delivery_number: string;
    delivery_date: string | null;
    supplier_name: string | null;
    plant_name: string | null;
    /** Only the materials this receipt carried; the service refuses others. */
    materials: SelectOption[];
}

export interface ProblemFormOptions {
    severities: SelectOption[];
    categories: SelectOption[];
}

export interface ProblemQueueSummary {
    open: number;
    overdue: number;
    critical: number;
}
