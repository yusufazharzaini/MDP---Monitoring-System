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
        'audit_log' => '監査ログ',
        'critical_material' => '重要資材',
        'dashboard' => 'ダッシュボード',
        'delivery' => '納品',
        'department' => '部門',
        'material' => '資材',
        'notification' => '通知',
        'overview' => '概要',
        'plant' => '工場',
        'problem_analysis' => '問題分析',
        'purchase_order' => '発注',
        'report' => 'レポート',
        'role_permission' => 'ロールと権限',
        'soon' => '近日公開',
        'supplier' => '仕入先',
        'supplier_evaluation' => '仕入先評価',
        'supplier_performance' => '仕入先パフォーマンス',
        'user' => 'ユーザー',
        'warehouse' => '倉庫',
    ],

    'auth' => [
        'email' => 'メールアドレス',
        'email_placeholder' => 'name@example.com',
        'password' => 'パスワード',
        'remember_me' => 'この端末でログイン状態を保持する',
        'sign_in' => 'ログイン',
        'sign_in_subtitle' => '会社アカウントでログインしてください。',
        'sign_in_title' => 'システムにログイン',
        'sign_out' => 'ログアウト',
        'tagline' => '仕入先から工場への資材納品パフォーマンスを管理します。サービス率、遅延、数量不足、問題分析、仕入先評価。',
    ],

    'common' => [
        'actions' => '操作',
        'address' => '住所',
        'approve' => '承認する',
        'approved_by' => '承認者',
        'back' => '戻る',
        'cancel' => 'キャンセル',
        'cancel_record' => '取り消す',
        'cancellation_reason' => '取消理由',
        'category' => 'カテゴリ',
        'city' => '市区町村',
        'condition' => '品質状態',
        'create' => '新規作成',
        'date' => '日付',
        'delete' => '削除',
        'department' => '部門',
        'description' => '説明',
        'details' => '詳細',
        'edit' => '編集',
        'email' => 'メールアドレス',
        'grade' => '評価',
        'item' => '明細',
        'language' => '言語',
        'module' => 'モジュール',
        'name' => '名称',
        'no_data' => 'データがありません',
        'notes' => '備考',
        'period' => '期間',
        'phone' => '電話番号',
        'position' => '役職',
        'quantity' => '数量',
        'rank' => '順位',
        'reason' => '理由',
        'role' => 'ロール',
        'root_cause' => '根本原因',
        'save' => '保存',
        'save_changes' => '変更を保存',
        'search' => '検索',
        'severity' => '重大度',
        'status' => 'ステータス',
        'target' => '目標',
        'to' => '〜',
        'total' => '合計',
        'unit' => '単位',
    ],

    'entity' => [
        'critical_material' => '重要資材',
        'delivery' => '納品',
        'material' => '資材',
        'plant' => '工場',
        'supplier' => '仕入先',
        'supplier_performance' => '仕入先パフォーマンス',
        'user' => 'ユーザー',
        'warehouse' => '倉庫',
    ],

    'po' => [
        'lead_time_days' => 'リードタイム（日）',
        'number' => '発注番号',
        'payment_term' => '支払条件',
        'pic_name' => '担当者名',
        'pic_phone' => '担当者電話番号',
        'qty' => '発注数量',
        'qty_received' => '受領数量',
        'schedule' => '納期',
    ],

    'action' => [
        'receive_goods' => '入荷登録',
    ],

    'state' => [
        'late' => '遅延',
        'on_time' => '納期内',
        'short' => '数量不足',
    ],

    'metric' => [
        'service_rate' => 'サービス率',
    ],

    'filter' => [
        'all_categories' => 'すべてのカテゴリ',
        'all_plants' => 'すべての工場',
        'all_status' => 'すべてのステータス',
        'all_suppliers' => 'すべての仕入先',
        'category' => 'カテゴリで絞り込む',
        'material_category' => '資材カテゴリで絞り込む',
        'plant' => '工場で絞り込む',
        'status' => 'ステータスで絞り込む',
        'supplier' => '仕入先で絞り込む',
    ],

    'select' => [
        'category' => 'カテゴリを選択',
        'plant' => '工場を選択',
    ],

    'msg' => [
        'check_marked_fields' => '下記の項目をご確認ください。',
        'fill_then_save' => '以下の項目を入力して保存してください。',
        'no_evaluation' => '評価がまだありません',
        'no_problem' => '問題はありません',
    ],

];
