{{--
    One template for both the PDF and the print view.

    DomPDF supports a narrow slice of CSS, so everything here is inline-safe:
    no flexbox, no grid, no custom properties. The same markup opened in a
    browser prints identically, which is what makes "print" and "PDF" the same
    document rather than two that drift apart.
--}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $dataset->title() }} — {{ $dataset->periodLabel() }}</title>
    <style>
        @page { margin: 14mm 10mm; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 8.5px;
            color: #1a1a1a;
            margin: 0;
        }
        .masthead { border-bottom: 1.5px solid #1a1a1a; padding-bottom: 6px; margin-bottom: 10px; }
        .org { font-size: 13px; font-weight: bold; letter-spacing: 2px; }
        .system { font-size: 8px; color: #555; }
        h1 { font-size: 12px; margin: 8px 0 2px; }
        .meta { font-size: 8px; color: #555; }
        .meta strong { color: #1a1a1a; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        thead { display: table-header-group; }
        th {
            background: #eceff3;
            border: 0.5px solid #b9c0cb;
            padding: 4px 5px;
            font-size: 7.5px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            text-align: left;
        }
        td { border: 0.5px solid #d4d9e0; padding: 3px 5px; vertical-align: top; }
        td.num, th.num { text-align: right; }
        tbody tr:nth-child(even) td { background: #f7f8fa; }
        .empty { padding: 18px; text-align: center; color: #666; border: 0.5px solid #d4d9e0; }
        .footer { margin-top: 10px; font-size: 7.5px; color: #666; border-top: 0.5px solid #d4d9e0; padding-top: 5px; }
        @media print { .no-print { display: none; } body { font-size: 9px; } }
    </style>
</head>
<body>
    <div class="masthead">
        <div class="org">PT. TORICA INDONESIA</div>
        <div class="system">Material Delivery Performance Monitoring System</div>
    </div>

    <h1>{{ $dataset->title() }}</h1>
    <div class="meta">
        Periode <strong>{{ $dataset->periodLabel() }}</strong>
        &middot; dicetak {{ $dataset->generatedAt->format('d/m/Y H:i') }}
        @if ($printedBy)
            &middot; oleh {{ $printedBy }}
        @endif
    </div>

    @if ($rows === [])
        <p class="empty">Tidak ada data pada periode ini.</p>
    @else
        <table>
            <thead>
                <tr>
                    @foreach ($dataset->columns as $column)
                        <th class="{{ $column->numeric ? 'num' : '' }}">{{ $column->label }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr>
                        @foreach ($dataset->columns as $column)
                            <td class="{{ $column->numeric ? 'num' : '' }}">
                                @if ($column->numeric)
                                    {{ number_format((float) ($row[$column->key] ?? 0), $column->decimals, ',', '.') }}
                                @else
                                    {{ $row[$column->key] ?? '-' }}
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="footer">
        {{ count($rows) }} baris
        @if ($truncated)
            &middot; dibatasi {{ number_format($limit, 0, ',', '.') }} baris pertama; gunakan ekspor Excel untuk data lengkap
        @endif
    </div>
</body>
</html>
