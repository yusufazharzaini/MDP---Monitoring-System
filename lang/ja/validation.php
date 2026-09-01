<?php

declare(strict_types=1);

/**
 * Validation messages.
 *
 * Covers every rule the application's form requests actually use, plus the
 * common ones. A rule with no entry here falls back to English rather than
 * rendering a key, so an untranslated exotic rule degrades to readable text.
 *
 * `attributes` matters as much as the messages: without it a translated
 * sentence still names the column, and the reader gets "Kolom critical_stock
 * wajib diisi" instead of a field they recognise from the form.
 */
return [

    'between' => [
        'array' => ':attribute は :min 件から :max 件にしてください。',
        'file' => ':attribute は :min KB から :max KB の間にしてください。',
        'numeric' => ':attribute は :min から :max の間で入力してください。',
        'string' => ':attribute は :min 文字から :max 文字で入力してください。',
    ],

    'gt' => [
        'array' => ':attribute は :value 件より多くしてください。',
        'file' => ':attribute は :value KB より大きくしてください。',
        'numeric' => ':attribute は :value より大きい値で入力してください。',
        'string' => ':attribute は :value 文字より多く入力してください。',
    ],

    'gte' => [
        'array' => ':attribute は :value 件以上にしてください。',
        'file' => ':attribute は :value KB 以上にしてください。',
        'numeric' => ':attribute は :value 以上で入力してください。',
        'string' => ':attribute は :value 文字以上で入力してください。',
    ],

    'lt' => [
        'array' => ':attribute は :value 件より少なくしてください。',
        'file' => ':attribute は :value KB より小さくしてください。',
        'numeric' => ':attribute は :value より小さい値で入力してください。',
        'string' => ':attribute は :value 文字より少なく入力してください。',
    ],

    'lte' => [
        'array' => ':attribute は :value 件以下にしてください。',
        'file' => ':attribute は :value KB 以下にしてください。',
        'numeric' => ':attribute は :value 以下で入力してください。',
        'string' => ':attribute は :value 文字以下で入力してください。',
    ],

    'max' => [
        'array' => ':attribute は :max 件以下にしてください。',
        'file' => ':attribute は :max KB 以下にしてください。',
        'numeric' => ':attribute は :max 以下で入力してください。',
        'string' => ':attribute は :max 文字以内で入力してください。',
    ],

    'min' => [
        'array' => ':attribute は :min 件以上にしてください。',
        'file' => ':attribute は :min KB 以上にしてください。',
        'numeric' => ':attribute は :min 以上で入力してください。',
        'string' => ':attribute は :min 文字以上で入力してください。',
    ],

    'size' => [
        'array' => ':attribute は :size 件にしてください。',
        'file' => ':attribute は :size KB にしてください。',
        'numeric' => ':attribute は :size にしてください。',
        'string' => ':attribute は :size 文字で入力してください。',
    ],

    'after_or_equal' => ':attribute は :date 以降の日付で入力してください。',
    'array' => ':attribute は配列で指定してください。',
    'before_or_equal' => ':attribute は :date 以前の日付で入力してください。',
    'boolean' => ':attribute は true または false で指定してください。',
    'confirmed' => ':attribute の確認が一致しません。',
    'date' => ':attribute は有効な日付で入力してください。',
    'date_format' => ':attribute は :format 形式で入力してください。',
    'distinct' => ':attribute の値が重複しています。',
    'email' => ':attribute は有効なメールアドレスで入力してください。',
    'exists' => '選択された :attribute は無効です。',
    'file' => ':attribute はファイルを指定してください。',
    'image' => ':attribute は画像を指定してください。',
    'in' => '選択された :attribute は無効です。',
    'integer' => ':attribute は整数で入力してください。',
    'mimes' => ':attribute は次の形式のファイルを指定してください：:values。',
    'not_in' => '選択された :attribute は無効です。',
    'numeric' => ':attribute は数値で入力してください。',
    'present' => ':attribute が存在している必要があります。',
    'regex' => ':attribute の形式が正しくありません。',
    'required' => ':attribute は必須です。',
    'required_if' => ':other が :value の場合、:attribute は必須です。',
    'string' => ':attribute は文字列で入力してください。',
    'unique' => ':attribute は既に使用されています。',
    'url' => ':attribute は有効な URL で入力してください。',

    'custom' => [],

    'attributes' => [
        'action_date' => '処置日',
        'address' => '住所',
        'category_id' => 'カテゴリ',
        'city' => '市区町村',
        'code' => 'コード',
        'country' => '国',
        'critical_stock' => '重要在庫水準',
        'currency' => '通貨',
        'date_from' => '開始日',
        'date_to' => '終了日',
        'delivery_date' => '納品日',
        'delivery_id' => '納品',
        'department_id' => '部門',
        'description' => '説明',
        'do_number' => '納品書番号',
        'driver_name' => 'ドライバー名',
        'due_date' => '期限',
        'email' => 'メールアドレス',
        'employee_code' => '社員番号',
        'is_critical' => '重要資材',
        'lead_time_days' => 'リードタイム（日）',
        'locale' => '言語',
        'material_id' => '資材',
        'minimum_stock' => '最小在庫',
        'name' => '名称',
        'notes' => '備考',
        'password' => 'パスワード',
        'phone' => '電話番号',
        'plant_id' => '工場',
        'po_date' => '発注日',
        'position' => '役職',
        'problem_id' => '問題',
        'quantity' => '数量',
        'reason' => '理由',
        'role' => 'ロール',
        'root_cause' => '根本原因',
        'severity' => '重大度',
        'status' => 'ステータス',
        'supplier_id' => '仕入先',
        'unit_price' => '単価',
        'uom_id' => '単位',
        'user_id' => 'ユーザー',
        'warehouse_id' => '倉庫',
    ],

];
