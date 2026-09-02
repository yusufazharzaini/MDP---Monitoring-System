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
        'CREATED' => '作成',
        'UPDATED' => '更新',
        'DELETED' => '削除',
        'RESTORED' => '復元',
        'SUBMITTED' => '申請',
        'APPROVED' => '承認',
        'CANCELLED' => '取消',
        'CLOSED' => 'クローズ',
        'IMPORTED' => 'インポート',
        'EXPORTED' => 'エクスポート',
        'LOGIN' => 'ログイン',
        'LOGOUT' => 'ログアウト',
    ],

    'CorrectiveActionStatus' => [
        'OPEN' => '未対応',
        'IN_PROGRESS' => '対応中',
        'DONE' => '完了',
    ],

    'DeliveryItemCondition' => [
        'GOOD' => '良品',
        'DAMAGED' => '破損',
        'REJECTED' => '不合格',
        'PARTIAL' => '一部',
    ],

    'DeliveryStatus' => [
        'PENDING' => '保留',
        'RECEIVED' => '受領済み',
        'PARTIAL' => '一部受領',
        'COMPLETED' => '完了',
        'CANCELLED' => '取消済み',
    ],

    'EvaluationStatus' => [
        'DRAFT' => '下書き',
        'APPROVED' => '承認済み',
    ],

    'OverallDeliveryStatus' => [
        'PENDING' => '保留',
        'ON_TIME_FULL' => '納期内 - 全量',
        'LATE_FULL' => '遅延 - 全量',
        'ON_TIME_SHORT' => '納期内 - 数量不足',
        'LATE_SHORT' => '遅延 - 数量不足',
        'OVER_DELIVERY' => '過納',
    ],

    'ProblemSeverity' => [
        'LOW' => '低',
        'MEDIUM' => '中',
        'HIGH' => '高',
        'CRITICAL' => '重大',
    ],

    'ProblemStatus' => [
        'OPEN' => '未対応',
        'IN_PROGRESS' => '対応中',
        'CLOSED' => 'クローズ',
        'CANCELLED' => '取消済み',
    ],

    'PurchaseOrderStatus' => [
        'DRAFT' => '下書き',
        'SUBMITTED' => '申請済み',
        'APPROVED' => '承認済み',
        'PARTIAL' => '一部納品',
        'COMPLETED' => '完了',
        'CANCELLED' => '取消済み',
    ],

    'QuantityStatus' => [
        'PENDING' => '保留',
        'SHORT' => '数量不足',
        'FULL' => '全量',
        'OVER' => '超過',
    ],

    'RecordStatus' => [
        'ACTIVE' => '有効',
        'INACTIVE' => '無効',
    ],

    'ReportType' => [
        'delivery' => '納品レポート',
        'purchase-order' => '発注レポート',
        'supplier-performance' => '仕入先パフォーマンスレポート',
        'problem' => '納品問題レポート',
        'critical-material' => '重要資材レポート',
    ],

    'RiskLevel' => [
        'LOW' => '低',
        'MEDIUM' => '中',
        'HIGH' => '高',
        'CRITICAL' => '重大',
    ],

    'SettingType' => [
        'STRING' => '文字列',
        'INTEGER' => '整数',
        'DECIMAL' => '小数',
        'BOOLEAN' => '真偽値',
        'JSON' => 'JSON',
    ],

    'SupplierGrade' => [
        'EXCELLENT' => '優秀',
        'GOOD' => '良好',
        'AVERAGE' => '普通',
        'POOR' => '不良',
    ],

    'SupplierStatus' => [
        'ACTIVE' => '有効',
        'INACTIVE' => '無効',
        'BLACKLISTED' => '取引停止',
    ],

    'SupplierType' => [
        'LOCAL' => '国内',
        'IMPORT' => '輸入',
        'TOLLING' => '委託加工',
        'SERVICE' => 'サービス',
    ],

    'TimelinessStatus' => [
        'PENDING' => '保留',
        'ON_TIME' => '納期内',
        'LATE' => '遅延',
    ],

    'UomType' => [
        'QTY' => '数量',
        'WEIGHT' => '重量',
        'VOLUME' => '体積',
        'LENGTH' => '長さ',
        'AREA' => '面積',
        'TIME' => '時間',
    ],

];
