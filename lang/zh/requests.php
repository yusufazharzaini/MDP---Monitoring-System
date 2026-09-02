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
    'action_date_future' => '纠正措施日期不能是未来日期。',
    'action_description_min' => '纠正措施说明至少需要 10 个字符。',
    'action_due_before_date' => '目标完成日期不能早于纠正措施日期。',
    'critical_stock_vs_min' => '关键库存必须小于或等于最低库存。',
    'date_to_before_from' => '结束日期不能早于开始日期。',
    'delivery_date_future' => '交货日期不能是未来日期。',
    'delivery_needs_lines' => '交货必须至少有一条收货明细。',
    'evaluation_period_future' => '只能为已开始的月份创建评估。',
    'evaluation_period_req' => '评估期间为必填项。',
    'password_mismatch' => '两次输入的密码不一致。',
    'period_format' => '期间必须为 YYYY-MM 格式。',
    'po_needs_lines' => '采购订单必须至少有一条明细。',
    'po_qty_positive' => '数量必须大于 0。',
    'po_schedule_before_date' => '计划交货日期不能早于订单日期。',
    'problem_date_future' => '问题日期不能是未来日期。',
    'problem_description_min' => '问题说明需描述事件经过，至少 10 个字符。',
    'problem_due_before_date' => '目标解决日期不能早于问题日期。',
    'report_format_invalid' => '报表格式必须是以下之一：:values。',
    'report_span_too_wide' => '报表跨度最多 :years 年。请缩小期间或按年下载。',
    'report_type_unknown' => '未知的报表类型。',
    'user_email_taken' => '该邮箱已被注册，包括已停用的账号。',
    'user_needs_role' => '用户必须至少拥有一个角色。',
];
