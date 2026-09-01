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
        'array' => ':attribute 必须有 :min 到 :max 项。',
        'file' => ':attribute 必须介于 :min 和 :max KB 之间。',
        'numeric' => ':attribute 必须介于 :min 和 :max 之间。',
        'string' => ':attribute 必须介于 :min 和 :max 个字符之间。',
    ],

    'gt' => [
        'array' => ':attribute 必须多于 :value 项。',
        'file' => ':attribute 必须大于 :value KB。',
        'numeric' => ':attribute 必须大于 :value。',
        'string' => ':attribute 必须多于 :value 个字符。',
    ],

    'gte' => [
        'array' => ':attribute 必须有 :value 项或更多。',
        'file' => ':attribute 必须大于或等于 :value KB。',
        'numeric' => ':attribute 必须大于或等于 :value。',
        'string' => ':attribute 必须多于或等于 :value 个字符。',
    ],

    'lt' => [
        'array' => ':attribute 必须少于 :value 项。',
        'file' => ':attribute 必须小于 :value KB。',
        'numeric' => ':attribute 必须小于 :value。',
        'string' => ':attribute 必须少于 :value 个字符。',
    ],

    'lte' => [
        'array' => ':attribute 不能超过 :value 项。',
        'file' => ':attribute 必须小于或等于 :value KB。',
        'numeric' => ':attribute 必须小于或等于 :value。',
        'string' => ':attribute 必须少于或等于 :value 个字符。',
    ],

    'max' => [
        'array' => ':attribute 不能超过 :max 项。',
        'file' => ':attribute 不能超过 :max KB。',
        'numeric' => ':attribute 不能大于 :max。',
        'string' => ':attribute 不能超过 :max 个字符。',
    ],

    'min' => [
        'array' => ':attribute 不能少于 :min 项。',
        'file' => ':attribute 不能小于 :min KB。',
        'numeric' => ':attribute 不能小于 :min。',
        'string' => ':attribute 不能少于 :min 个字符。',
    ],

    'size' => [
        'array' => ':attribute 必须有 :size 项。',
        'file' => ':attribute 必须为 :size KB。',
        'numeric' => ':attribute 必须为 :size。',
        'string' => ':attribute は :size 个字符。',
    ],

    'after_or_equal' => ':attribute 必须是 :date 或之后的日期。',
    'array' => ':attribute 必须是数组。',
    'before_or_equal' => ':attribute 必须是 :date 或之前的日期。',
    'boolean' => ':attribute 必须为真或假。',
    'confirmed' => ':attribute 的确认不匹配。',
    'date' => ':attribute 必须是有效日期。',
    'date_format' => ':attribute 必须符合 :format 格式。',
    'distinct' => ':attribute 的值重复。',
    'email' => ':attribute 必须是有效的邮箱地址。',
    'exists' => '所选的 :attribute 无效。',
    'file' => ':attribute 必须是文件。',
    'image' => ':attribute 必须是图片。',
    'in' => '所选的 :attribute 无效。',
    'integer' => ':attribute 必须是整数。',
    'mimes' => ':attribute 必须是以下类型的文件：:values。',
    'not_in' => '所选的 :attribute 无效。',
    'numeric' => ':attribute 必须是数字。',
    'present' => ':attribute 必须存在。',
    'regex' => ':attribute 的格式无效。',
    'required' => ':attribute 为必填项。',
    'required_if' => '当 :other 为 :value 时，:attribute 为必填项。',
    'string' => ':attribute 必须是字符串。',
    'unique' => ':attribute 已被使用。',
    'url' => ':attribute 必须是有效的网址。',

    'custom' => [],

    'attributes' => [
        'action_date' => '措施日期',
        'address' => '地址',
        'category_id' => '类别',
        'city' => '城市',
        'code' => '编码',
        'country' => '国家',
        'critical_stock' => '关键库存',
        'currency' => '币种',
        'date_from' => '开始日期',
        'date_to' => '结束日期',
        'delivery_date' => '交货日期',
        'delivery_id' => '交货',
        'department_id' => '部门',
        'description' => '说明',
        'do_number' => '送货单号',
        'driver_name' => '司机姓名',
        'due_date' => '截止日期',
        'email' => '邮箱',
        'employee_code' => '工号',
        'is_critical' => '关键物料',
        'lead_time_days' => '交货周期（天）',
        'locale' => '语言',
        'material_id' => '物料',
        'minimum_stock' => '最低库存',
        'name' => '名称',
        'notes' => '备注',
        'password' => '密码',
        'phone' => '电话',
        'plant_id' => '工厂',
        'po_date' => '订单日期',
        'position' => '职位',
        'problem_id' => '问题',
        'quantity' => '数量',
        'reason' => '原因',
        'role' => '角色',
        'root_cause' => '根本原因',
        'severity' => '严重程度',
        'status' => '状态',
        'supplier_id' => '供应商',
        'unit_price' => '单价',
        'uom_id' => '单位',
        'user_id' => '用户',
        'warehouse_id' => '仓库',
    ],

];
