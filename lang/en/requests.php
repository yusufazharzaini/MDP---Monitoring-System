<?php

declare(strict_types=1);

/**
 * Messages a form request overrides.
 *
 * These are business rules stated in words - "critical stock must be less than
 * or equal to minimum stock" - rather than generic rule failures, so they live
 * here rather than in validation.php, and each form request looks them up by
 * key instead of carrying the sentence itself.
 */
return [
    'action_date_future' => 'The corrective action date cannot be in the future.',
    'action_description_min' => 'A corrective action must be described, at least 10 characters.',
    'action_due_before_date' => 'The target completion cannot come before the corrective action date.',
    'critical_stock_vs_min' => 'Critical stock must be less than or equal to minimum stock.',
    'date_to_before_from' => 'The end date cannot come before the start date.',
    'delivery_date_future' => 'The delivery date cannot be in the future.',
    'delivery_needs_lines' => 'A delivery must have at least one receipt line.',
    'evaluation_period_future' => 'An evaluation can only be created for a month that has already started.',
    'evaluation_period_req' => 'The evaluation period is required.',
    'password_mismatch' => 'The password confirmation does not match.',
    'period_format' => 'The period must be in YYYY-MM format.',
    'po_needs_lines' => 'A purchase order must have at least one line item.',
    'po_qty_positive' => 'Quantity must be greater than 0.',
    'po_schedule_before_date' => 'The scheduled delivery cannot come before the PO date.',
    'problem_date_future' => 'The problem date cannot be in the future.',
    'problem_description_min' => 'The problem description must explain what happened, at least 10 characters.',
    'problem_due_before_date' => 'The target resolution cannot come before the problem date.',
    'report_format_invalid' => 'The report format must be one of: :values.',
    'report_span_too_wide' => 'A report may span at most :years year(s). Narrow the period, or download it a year at a time.',
    'report_type_unknown' => 'That report type is not recognised.',
    'user_email_taken' => 'This email address is already registered, including on a deactivated account.',
    'user_needs_role' => 'A user must hold at least one role.',
];
