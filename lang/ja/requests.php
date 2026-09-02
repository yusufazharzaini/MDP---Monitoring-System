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
    'action_date_future' => '是正処置の日付を未来にすることはできません。',
    'action_description_min' => '是正処置は10文字以上で記載してください。',
    'action_due_before_date' => '完了予定日は是正処置日より前にできません。',
    'critical_stock_vs_min' => '重要在庫水準は最小在庫以下にしてください。',
    'date_to_before_from' => '終了日は開始日より前にできません。',
    'delivery_date_future' => '納品日を未来の日付にすることはできません。',
    'delivery_needs_lines' => '納品には少なくとも1件の受領明細が必要です。',
    'evaluation_period_future' => '評価は既に開始した月に対してのみ作成できます。',
    'evaluation_period_req' => '評価期間は必須です。',
    'password_mismatch' => 'パスワードの確認が一致しません。',
    'period_format' => '期間は YYYY-MM 形式で入力してください。',
    'po_needs_lines' => '発注には少なくとも1件の明細が必要です。',
    'po_qty_positive' => '数量は0より大きい値にしてください。',
    'po_schedule_before_date' => '納期を発注日より前にすることはできません。',
    'problem_date_future' => '問題の発生日を未来にすることはできません。',
    'problem_description_min' => '問題の説明は何が起きたかを10文字以上で記載してください。',
    'problem_due_before_date' => '解決予定日は発生日より前にできません。',
    'report_format_invalid' => 'レポート形式は次のいずれかにしてください：:values。',
    'report_span_too_wide' => 'レポートの期間は最大 :years 年です。期間を狭めるか、年ごとにダウンロードしてください。',
    'report_type_unknown' => '不明なレポート種別です。',
    'user_email_taken' => 'このメールアドレスは既に登録されています（停止中のアカウントを含む）。',
    'user_needs_role' => 'ユーザーには少なくとも1つのロールが必要です。',
];
