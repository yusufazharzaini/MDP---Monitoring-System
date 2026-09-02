<?php

declare(strict_types=1);

/**
 * Enum labels - the controlled vocabulary an operator reads all day.
 *
 * Resolved by App\Enums\Concerns\HasEnumMetadata::label(). Keys mirror the
 * enum class name and its case value, so a new case shows up as a missing key
 * in LocaleTest rather than silently rendering title-cased English.
 */
return [

    'AuditAction' => [
        'CREATED' => 'Created',
        'UPDATED' => 'Updated',
        'DELETED' => 'Deleted',
        'RESTORED' => 'Restored',
        'SUBMITTED' => 'Submitted',
        'APPROVED' => 'Approved',
        'CANCELLED' => 'Cancelled',
        'CLOSED' => 'Closed',
        'IMPORTED' => 'Imported',
        'EXPORTED' => 'Exported',
        'LOGIN' => 'Login',
        'LOGOUT' => 'Logout',
    ],

    'CorrectiveActionStatus' => [
        'OPEN' => 'Open',
        'IN_PROGRESS' => 'In Progress',
        'DONE' => 'Done',
    ],

    'DeliveryItemCondition' => [
        'GOOD' => 'Good',
        'DAMAGED' => 'Damaged',
        'REJECTED' => 'Rejected',
        'PARTIAL' => 'Partial',
    ],

    'DeliveryStatus' => [
        'PENDING' => 'Pending',
        'RECEIVED' => 'Received',
        'PARTIAL' => 'Partial',
        'COMPLETED' => 'Completed',
        'CANCELLED' => 'Cancelled',
    ],

    'EvaluationStatus' => [
        'DRAFT' => 'Draft',
        'APPROVED' => 'Approved',
    ],

    'OverallDeliveryStatus' => [
        'PENDING' => 'Pending',
        'ON_TIME_FULL' => 'On Time - Full',
        'LATE_FULL' => 'Late - Full',
        'ON_TIME_SHORT' => 'On Time - Short',
        'LATE_SHORT' => 'Late - Short',
        'OVER_DELIVERY' => 'Over Delivery',
    ],

    'ProblemSeverity' => [
        'LOW' => 'Low',
        'MEDIUM' => 'Medium',
        'HIGH' => 'High',
        'CRITICAL' => 'Critical',
    ],

    'ProblemStatus' => [
        'OPEN' => 'Open',
        'IN_PROGRESS' => 'In Progress',
        'CLOSED' => 'Closed',
        'CANCELLED' => 'Cancelled',
    ],

    'PurchaseOrderStatus' => [
        'DRAFT' => 'Draft',
        'SUBMITTED' => 'Submitted',
        'APPROVED' => 'Approved',
        'PARTIAL' => 'Partial',
        'COMPLETED' => 'Completed',
        'CANCELLED' => 'Cancelled',
    ],

    'QuantityStatus' => [
        'PENDING' => 'Pending',
        'SHORT' => 'Short',
        'FULL' => 'Full',
        'OVER' => 'Over',
    ],

    'RecordStatus' => [
        'ACTIVE' => 'Active',
        'INACTIVE' => 'Inactive',
    ],

    'ReportType' => [
        'delivery' => 'Delivery Report',
        'purchase-order' => 'Purchase Order Report',
        'supplier-performance' => 'Supplier Performance Report',
        'problem' => 'Delivery Problem Report',
        'critical-material' => 'Critical Material Report',
    ],

    'RiskLevel' => [
        'LOW' => 'Low',
        'MEDIUM' => 'Medium',
        'HIGH' => 'High',
        'CRITICAL' => 'Critical',
    ],

    'SettingType' => [
        'STRING' => 'String',
        'INTEGER' => 'Integer',
        'DECIMAL' => 'Decimal',
        'BOOLEAN' => 'Boolean',
        'JSON' => 'Json',
    ],

    'SupplierGrade' => [
        'EXCELLENT' => 'Excellent',
        'GOOD' => 'Good',
        'AVERAGE' => 'Average',
        'POOR' => 'Poor',
    ],

    'SupplierStatus' => [
        'ACTIVE' => 'Active',
        'INACTIVE' => 'Inactive',
        'BLACKLISTED' => 'Blacklisted',
    ],

    'SupplierType' => [
        'LOCAL' => 'Local',
        'IMPORT' => 'Import',
        'TOLLING' => 'Tolling',
        'SERVICE' => 'Service',
    ],

    'TimelinessStatus' => [
        'PENDING' => 'Pending',
        'ON_TIME' => 'On Time',
        'LATE' => 'Late',
    ],

    'UomType' => [
        'QTY' => 'Qty',
        'WEIGHT' => 'Weight',
        'VOLUME' => 'Volume',
        'LENGTH' => 'Length',
        'AREA' => 'Area',
        'TIME' => 'Time',
    ],

];
