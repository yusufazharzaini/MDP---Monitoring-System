<?php

declare(strict_types=1);

/**
 * Interface strings.
 *
 * The whole file is handed to the browser through Inertia's shared props for
 * the active locale, so one language's worth of text crosses the wire, not four.
 * Business data - supplier names, material codes, problem descriptions - is
 * never translated: it is the record the audit trail is kept against.
 */
return [

    'nav' => [
        'audit_log' => 'Audit Log',
        'critical_material' => 'Critical Material',
        'dashboard' => 'Dashboard',
        'delivery' => 'Delivery',
        'department' => 'Department',
        'material' => 'Material',
        'notification' => 'Notification',
        'overview' => 'Overview',
        'plant' => 'Plant',
        'problem_analysis' => 'Problem Analysis',
        'purchase_order' => 'Purchase Order',
        'report' => 'Report',
        'role_permission' => 'Roles & Permissions',
        'soon' => 'Soon',
        'supplier' => 'Supplier',
        'supplier_evaluation' => 'Supplier Evaluation',
        'supplier_performance' => 'Supplier Performance',
        'user' => 'User',
        'warehouse' => 'Warehouse',
    ],

    'auth' => [
        'email' => 'Email',
        'email_placeholder' => 'name@example.com',
        'password' => 'Password',
        'remember_me' => 'Remember me on this device',
        'sign_in' => 'Sign in',
        'sign_in_subtitle' => 'Use your company account to continue.',
        'sign_in_title' => 'Sign in to the system',
        'sign_out' => 'Sign out',
        'tagline' => 'Track material delivery performance from suppliers into plants: service rate, lateness, quantity shortage, problem analysis and supplier performance.',
    ],

    'common' => [
        'actions' => 'Actions',
        'address' => 'Address',
        'approve' => 'Approve',
        'approved_by' => 'Approved by',
        'back' => 'Back',
        'cancel' => 'Cancel',
        'cancel_record' => 'Cancel',
        'cancellation_reason' => 'Cancellation reason',
        'category' => 'Category',
        'city' => 'City',
        'condition' => 'Condition',
        'create' => 'Create',
        'date' => 'Date',
        'delete' => 'Delete',
        'department' => 'Department',
        'description' => 'Description',
        'details' => 'Details',
        'edit' => 'Edit',
        'email' => 'Email',
        'grade' => 'Grade',
        'item' => 'Item',
        'language' => 'Language',
        'module' => 'Module',
        'name' => 'Name',
        'no_data' => 'No data yet',
        'notes' => 'Notes',
        'period' => 'Period',
        'phone' => 'Phone',
        'position' => 'Position',
        'quantity' => 'Quantity',
        'rank' => 'Rank',
        'reason' => 'Reason',
        'role' => 'Role',
        'root_cause' => 'Root cause',
        'save' => 'Save',
        'save_changes' => 'Save changes',
        'search' => 'Search',
        'severity' => 'Severity',
        'status' => 'Status',
        'target' => 'Target',
        'to' => 'to',
        'total' => 'Total',
        'unit' => 'Unit',
    ],

    'entity' => [
        'critical_material' => 'Critical Material',
        'delivery' => 'Delivery',
        'material' => 'Material',
        'plant' => 'Plant',
        'supplier' => 'Supplier',
        'supplier_performance' => 'Supplier Performance',
        'user' => 'User',
        'warehouse' => 'Warehouse',
    ],

    'po' => [
        'lead_time_days' => 'Lead time (days)',
        'number' => 'PO No.',
        'payment_term' => 'Payment term',
        'pic_name' => 'Contact name',
        'pic_phone' => 'Contact phone',
        'qty' => 'PO Qty',
        'qty_received' => 'Received Qty',
        'schedule' => 'Schedule',
    ],

    'action' => [
        'receive_goods' => 'Receive goods',
    ],

    'state' => [
        'late' => 'Late',
        'on_time' => 'On Time',
        'short' => 'Short',
    ],

    'metric' => [
        'service_rate' => 'Service Rate',
    ],

    'filter' => [
        'all_categories' => 'All categories',
        'all_plants' => 'All plants',
        'all_status' => 'All statuses',
        'all_suppliers' => 'All suppliers',
        'category' => 'Filter by category',
        'material_category' => 'Filter by material category',
        'plant' => 'Filter by plant',
        'status' => 'Filter by status',
        'supplier' => 'Filter by supplier',
    ],

    'select' => [
        'category' => 'Select a category',
        'plant' => 'Select a plant',
    ],

    'msg' => [
        'check_marked_fields' => 'Check the fields marked below.',
        'fill_then_save' => 'Fill in the fields below, then save.',
        'no_evaluation' => 'No evaluations yet',
        'no_problem' => 'No problems',
    ],

];
