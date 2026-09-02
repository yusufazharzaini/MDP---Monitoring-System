<?php

declare(strict_types=1);

/**
 * The languages the interface is offered in.
 *
 * `native` is what the switcher shows: a reader looking for their own language
 * finds it faster written the way they write it than translated into the
 * language they cannot read.
 *
 * Adding a language means adding a row here and a directory under lang/. The
 * LocaleTest fails if a directory is missing a key that English defines, so a
 * half-finished translation cannot ship silently.
 */
return [

    'supported' => [
        'en' => ['native' => 'English', 'english' => 'English'],
        'id' => ['native' => 'Bahasa Indonesia', 'english' => 'Indonesian'],
        'ja' => ['native' => '日本語', 'english' => 'Japanese'],
        'zh' => ['native' => '简体中文', 'english' => 'Chinese (Simplified)'],
    ],

    /*
     * Printed documents stay in one language regardless of who exports them.
     *
     * DomPDF renders with DejaVu Sans, which carries no CJK glyphs at all - a
     * Japanese report would come out as empty boxes rather than as an error,
     * which is the kind of failure that reaches a manager's desk unnoticed.
     * Excel exports are unaffected: PhpSpreadsheet writes UTF-8 happily.
     */
    'documents' => 'en',

];
