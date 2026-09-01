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
        'overview' => '概览',
        'dashboard' => '仪表板',
        'supplier' => '供应商',
        'plant' => '工厂',
        'warehouse' => '仓库',
        'material' => '物料',
        'department' => '部门',
        'purchase_order' => '采购订单',
        'delivery' => '交货',
        'problem_analysis' => '问题分析',
        'supplier_performance' => '供应商绩效',
        'supplier_evaluation' => '供应商评估',
        'critical_material' => '关键物料',
        'report' => '报表',
        'user' => '用户',
        'role_permission' => '角色与权限',
        'audit_log' => '审计日志',
        'notification' => '通知',
        'soon' => '即将推出',
    ],

    'auth' => [
        'email' => '邮箱',
        'password' => '密码',
        'sign_in' => '登录',
        'sign_out' => '登出',
        'email_placeholder' => 'name@example.com',
        'sign_in_title' => '登录系统',
        'sign_in_subtitle' => '请使用公司账号继续。',
        'remember_me' => '在此设备上记住我',
        'tagline' => '跟踪从供应商到工厂的物料交货绩效：服务率、延迟、数量不足、问题分析与供应商绩效。',
    ],

    'common' => [
        'language' => '语言',
        'search' => '搜索',
        'save' => '保存',
        'cancel' => '取消',
        'create' => '新建',
        'edit' => '编辑',
        'delete' => '删除',
        'back' => '返回',
        'actions' => '操作',
        'no_data' => '暂无数据',
        'to' => '至',
    ],

];
