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
        'audit_log' => '审计日志',
        'critical_material' => '关键物料',
        'dashboard' => '仪表板',
        'delivery' => '交货',
        'department' => '部门',
        'material' => '物料',
        'notification' => '通知',
        'overview' => '概览',
        'plant' => '工厂',
        'problem_analysis' => '问题分析',
        'purchase_order' => '采购订单',
        'report' => '报表',
        'role_permission' => '角色与权限',
        'soon' => '即将推出',
        'supplier' => '供应商',
        'supplier_evaluation' => '供应商评估',
        'supplier_performance' => '供应商绩效',
        'user' => '用户',
        'warehouse' => '仓库',
    ],

    'auth' => [
        'email' => '邮箱',
        'email_placeholder' => 'name@example.com',
        'password' => '密码',
        'remember_me' => '在此设备上记住我',
        'sign_in' => '登录',
        'sign_in_subtitle' => '请使用公司账号继续。',
        'sign_in_title' => '登录系统',
        'sign_out' => '登出',
        'tagline' => '跟踪从供应商到工厂的物料交货绩效：服务率、延迟、数量不足、问题分析与供应商绩效。',
    ],

    'common' => [
        'actions' => '操作',
        'address' => '地址',
        'approve' => '批准',
        'approved_by' => '批准人',
        'back' => '返回',
        'cancel' => '取消',
        'cancel_record' => '取消',
        'cancellation_reason' => '取消原因',
        'category' => '类别',
        'city' => '城市',
        'condition' => '货品状况',
        'create' => '新建',
        'date' => '日期',
        'delete' => '删除',
        'department' => '部门',
        'description' => '说明',
        'details' => '详情',
        'edit' => '编辑',
        'email' => '邮箱',
        'grade' => '等级',
        'item' => '明细',
        'language' => '语言',
        'module' => '模块',
        'name' => '名称',
        'no_data' => '暂无数据',
        'notes' => '备注',
        'period' => '期间',
        'phone' => '电话',
        'position' => '职位',
        'quantity' => '数量',
        'rank' => '排名',
        'reason' => '原因',
        'role' => '角色',
        'root_cause' => '根本原因',
        'save' => '保存',
        'save_changes' => '保存更改',
        'search' => '搜索',
        'severity' => '严重程度',
        'status' => '状态',
        'target' => '目标',
        'to' => '至',
        'total' => '合计',
        'unit' => '单位',
    ],

    'entity' => [
        'critical_material' => '关键物料',
        'delivery' => '交货',
        'material' => '物料',
        'plant' => '工厂',
        'supplier' => '供应商',
        'supplier_performance' => '供应商绩效',
        'user' => '用户',
        'warehouse' => '仓库',
    ],

    'po' => [
        'lead_time_days' => '交货周期（天）',
        'number' => '订单号',
        'payment_term' => '付款条件',
        'pic_name' => '联系人姓名',
        'pic_phone' => '联系人电话',
        'qty' => '订单数量',
        'qty_received' => '接收数量',
        'schedule' => '计划日期',
    ],

    'action' => [
        'receive_goods' => '收货',
    ],

    'state' => [
        'late' => '延迟',
        'on_time' => '准时',
        'short' => '数量不足',
    ],

    'metric' => [
        'service_rate' => '服务率',
    ],

    'filter' => [
        'all_categories' => '全部类别',
        'all_plants' => '全部工厂',
        'all_status' => '全部状态',
        'all_suppliers' => '全部供应商',
        'category' => '按类别筛选',
        'material_category' => '按物料类别筛选',
        'plant' => '按工厂筛选',
        'status' => '按状态筛选',
        'supplier' => '按供应商筛选',
    ],

    'select' => [
        'category' => '选择类别',
        'plant' => '选择工厂',
    ],

    'msg' => [
        'check_marked_fields' => '请检查下方标记的字段。',
        'fill_then_save' => '请填写以下内容后保存。',
        'no_evaluation' => '暂无评估',
        'no_problem' => '没有问题',
    ],

];
