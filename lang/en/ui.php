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
        'overview' => 'Overview',
        'dashboard' => 'Dashboard',
        'supplier' => 'Supplier',
        'plant' => 'Plant',
        'warehouse' => 'Warehouse',
        'material' => 'Material',
        'department' => 'Department',
        'purchase_order' => 'Purchase Order',
        'delivery' => 'Delivery',
        'problem_analysis' => 'Problem Analysis',
        'supplier_performance' => 'Supplier Performance',
        'supplier_evaluation' => 'Supplier Evaluation',
        'critical_material' => 'Critical Material',
        'report' => 'Report',
        'user' => 'User',
        'role_permission' => 'Roles & Permissions',
        'audit_log' => 'Audit Log',
        'notification' => 'Notification',
        'soon' => 'Soon',
    ],

    'auth' => [
        'email' => 'Email',
        'password' => 'Password',
        'sign_in' => 'Sign in',
        'sign_out' => 'Sign out',
        'email_placeholder' => 'name@example.com',
        'sign_in_title' => 'Sign in to the system',
        'sign_in_subtitle' => 'Use your company account to continue.',
        'remember_me' => 'Remember me on this device',
        'tagline' => 'Track material delivery performance from suppliers into plants: service rate, lateness, quantity shortage, problem analysis and supplier performance.',
    ],

    'common' => [
        'language' => 'Language',
        'search' => 'Search',
        'save' => 'Save',
        'cancel' => 'Cancel',
        'create' => 'Create',
        'edit' => 'Edit',
        'delete' => 'Delete',
        'back' => 'Back',
        'actions' => 'Actions',
        'no_data' => 'No data yet',
        'to' => 'to',
    ],

];
