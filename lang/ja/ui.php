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
        'overview' => '概要',
        'dashboard' => 'ダッシュボード',
        'supplier' => '仕入先',
        'plant' => '工場',
        'warehouse' => '倉庫',
        'material' => '資材',
        'department' => '部門',
        'purchase_order' => '発注',
        'delivery' => '納品',
        'problem_analysis' => '問題分析',
        'supplier_performance' => '仕入先パフォーマンス',
        'supplier_evaluation' => '仕入先評価',
        'critical_material' => '重要資材',
        'report' => 'レポート',
        'user' => 'ユーザー',
        'role_permission' => 'ロールと権限',
        'audit_log' => '監査ログ',
        'notification' => '通知',
        'soon' => '近日公開',
    ],

    'auth' => [
        'email' => 'メールアドレス',
        'password' => 'パスワード',
        'sign_in' => 'ログイン',
        'sign_out' => 'ログアウト',
        'email_placeholder' => 'name@example.com',
        'sign_in_title' => 'システムにログイン',
        'sign_in_subtitle' => '会社アカウントでログインしてください。',
        'remember_me' => 'この端末でログイン状態を保持する',
        'tagline' => '仕入先から工場への資材納品パフォーマンスを管理します。サービス率、遅延、数量不足、問題分析、仕入先評価。',
    ],

    'common' => [
        'language' => '言語',
        'search' => '検索',
        'save' => '保存',
        'cancel' => 'キャンセル',
        'create' => '新規作成',
        'edit' => '編集',
        'delete' => '削除',
        'back' => '戻る',
        'actions' => '操作',
        'no_data' => 'データがありません',
        'to' => '〜',
    ],

];
