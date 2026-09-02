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
        'CREATED' => '创建',
        'UPDATED' => '更新',
        'DELETED' => '删除',
        'RESTORED' => '恢复',
        'SUBMITTED' => '提交',
        'APPROVED' => '批准',
        'CANCELLED' => '取消',
        'CLOSED' => '关闭',
        'IMPORTED' => '导入',
        'EXPORTED' => '导出',
        'LOGIN' => '登录',
        'LOGOUT' => '登出',
    ],

    'CorrectiveActionStatus' => [
        'OPEN' => '待处理',
        'IN_PROGRESS' => '处理中',
        'DONE' => '已完成',
    ],

    'DeliveryItemCondition' => [
        'GOOD' => '良好',
        'DAMAGED' => '破损',
        'REJECTED' => '不合格',
        'PARTIAL' => '部分',
    ],

    'DeliveryStatus' => [
        'PENDING' => '待处理',
        'RECEIVED' => '已接收',
        'PARTIAL' => '部分接收',
        'COMPLETED' => '已完成',
        'CANCELLED' => '已取消',
    ],

    'EvaluationStatus' => [
        'DRAFT' => '草稿',
        'APPROVED' => '已批准',
    ],

    'OverallDeliveryStatus' => [
        'PENDING' => '待处理',
        'ON_TIME_FULL' => '准时 - 全量',
        'LATE_FULL' => '延迟 - 全量',
        'ON_TIME_SHORT' => '准时 - 数量不足',
        'LATE_SHORT' => '延迟 - 数量不足',
        'OVER_DELIVERY' => '超量交货',
    ],

    'ProblemSeverity' => [
        'LOW' => '低',
        'MEDIUM' => '中',
        'HIGH' => '高',
        'CRITICAL' => '严重',
    ],

    'ProblemStatus' => [
        'OPEN' => '待处理',
        'IN_PROGRESS' => '处理中',
        'CLOSED' => '已关闭',
        'CANCELLED' => '已取消',
    ],

    'PurchaseOrderStatus' => [
        'DRAFT' => '草稿',
        'SUBMITTED' => '已提交',
        'APPROVED' => '已批准',
        'PARTIAL' => '部分交货',
        'COMPLETED' => '已完成',
        'CANCELLED' => '已取消',
    ],

    'QuantityStatus' => [
        'PENDING' => '待处理',
        'SHORT' => '数量不足',
        'FULL' => '全量',
        'OVER' => '超量',
    ],

    'RecordStatus' => [
        'ACTIVE' => '启用',
        'INACTIVE' => '停用',
    ],

    'ReportType' => [
        'delivery' => '交货报表',
        'purchase-order' => '采购订单报表',
        'supplier-performance' => '供应商绩效报表',
        'problem' => '交货问题报表',
        'critical-material' => '关键物料报表',
    ],

    'RiskLevel' => [
        'LOW' => '低',
        'MEDIUM' => '中',
        'HIGH' => '高',
        'CRITICAL' => '严重',
    ],

    'SettingType' => [
        'STRING' => '字符串',
        'INTEGER' => '整数',
        'DECIMAL' => '小数',
        'BOOLEAN' => '布尔值',
        'JSON' => 'JSON',
    ],

    'SupplierGrade' => [
        'EXCELLENT' => '优秀',
        'GOOD' => '良好',
        'AVERAGE' => '一般',
        'POOR' => '较差',
    ],

    'SupplierStatus' => [
        'ACTIVE' => '启用',
        'INACTIVE' => '停用',
        'BLACKLISTED' => '黑名单',
    ],

    'SupplierType' => [
        'LOCAL' => '本地',
        'IMPORT' => '进口',
        'TOLLING' => '来料加工',
        'SERVICE' => '服务',
    ],

    'TimelinessStatus' => [
        'PENDING' => '待处理',
        'ON_TIME' => '准时',
        'LATE' => '延迟',
    ],

    'UomType' => [
        'QTY' => '数量',
        'WEIGHT' => '重量',
        'VOLUME' => '体积',
        'LENGTH' => '长度',
        'AREA' => '面积',
        'TIME' => '时间',
    ],

];
