<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">

    <title>
        Tagihan {{ $document['number'] }}
    </title>

    @php
        $backgroundDataUri = null;

        $backgroundPath =
            ($template['package_path'] ?? '')
            . '/'
            . ($template['background']['asset'] ?? '');

        if (
            ($template['background'] ?? null)
            && is_file($backgroundPath)
        ) {
            $backgroundContents =
                file_get_contents(
                    $backgroundPath
                );

            if (
                $backgroundContents !== false
                && $backgroundContents !== ''
            ) {
                $backgroundDataUri =
                    'data:image/png;base64,'
                    . base64_encode(
                        $backgroundContents
                    );
            }
        }

        $logoRules =
            $template['logo']
            ?? [];

        $showLogo =
            ($logoRules['show'] ?? false)
            && ! empty(
                $branding['logo_data_uri']
            );
    @endphp

    <style>
        @page {
            margin: 0;
        }

        html,
        body {
            margin: 0;
            padding: 0;
            font-family: DejaVu Sans, sans-serif;
            color: {{ $theme['text'] ?? '#172033' }};
            font-size: 10px;
            line-height: 1.45;
        }

        body {
            position: relative;
        }

        .page-background {
            position: fixed;
            top: 0;
            left: 0;
            width: 210mm;
            height: 297mm;
            z-index: -10;
        }

        .page-background img {
            width: 210mm;
            height: 297mm;
        }

        .page {
            padding:
                23mm
                16mm
                20mm
                16mm;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        .header td {
            vertical-align: middle;
        }

        .logo {
            display: block;
            max-width:
                {{ (int) ($logoRules['max_width'] ?? 155) }}px;
            max-height:
                {{ (int) ($logoRules['max_height'] ?? 64) }}px;
            margin-bottom: 8px;
        }

        .business-name {
            font-size: 18px;
            font-weight: bold;
            color:
                {{ $theme['primary_dark'] ?? '#164E63' }};
        }

        .business-meta {
            margin-top: 5px;
            color:
                {{ $theme['muted'] ?? '#667085' }};
            font-size: 8.7px;
            line-height: 1.5;
        }

        .document-box {
            margin-left: auto;
            padding: 12px 14px;
            border:
                1px solid
                {{ $theme['border'] ?? '#D8E3EA' }};
            border-left:
                5px solid
                {{ $theme['accent'] ?? '#38BDF8' }};
            background:
                {{ $theme['surface'] ?? '#F5FAFC' }};
            text-align: right;
        }

        .document-label {
            color:
                {{ $theme['muted'] ?? '#64748B' }};
            font-size: 8px;
            font-weight: bold;
            letter-spacing: .8px;
            text-transform: uppercase;
        }

        .document-number {
            margin-top: 3px;
            color:
                {{ $theme['primary_dark'] ?? '#164E63' }};
            font-size: 15px;
            font-weight: bold;
        }

        .document-status {
            margin-top: 7px;
        }

        .bill-to,
        .meta-card {
            border:
                1px solid
                {{ $theme['border'] ?? '#D8E3EA' }};
            background: #fff;
            padding: 12px 14px;
        }

        .bill-to {
            border-top:
                3px solid
                {{ $theme['accent'] ?? '#38BDF8' }};
        }

        .meta-card {
            border-top:
                3px solid
                {{ $theme['primary'] ?? '#155E75' }};
        }

        .label {
            color:
                {{ $theme['muted'] ?? '#667085' }};
            font-size: 8px;
            text-transform: uppercase;
            letter-spacing: .7px;
        }

        .customer-name {
            margin-top: 4px;
            font-size: 13px;
            font-weight: bold;
        }

        .meta td {
            padding: 2px 0;
        }

        .meta-label {
            color:
                {{ $theme['muted'] ?? '#667085' }};
        }

        .status {
            display: inline-block;
            padding: 3px 7px;
            background:
                {{ $theme['primary'] ?? '#155E75' }};
            color: #fff;
            font-size: 8px;
            font-weight: bold;
        }

        .items {
            margin-top: 16px;
        }

        .items th {
            padding: 8px 7px;
            color:
                {{ $theme['primary_dark'] ?? '#164E63' }};
            background:
                {{ $theme['surface'] ?? '#F5FAFC' }};
            border-top:
                2px solid
                {{ $theme['primary'] ?? '#155E75' }};
            border-bottom:
                1px solid
                {{ $theme['border'] ?? '#D8E3EA' }};
            font-size: 8px;
            text-transform: uppercase;
            letter-spacing: .45px;
            text-align: left;
        }

        .items td {
            padding: 9px 6px;
            vertical-align: top;
            border-bottom: 1px solid
                {{ $theme['border'] ?? '#D7DEE8' }};
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .muted {
            margin-top: 2px;
            color:
                {{ $theme['muted'] ?? '#667085' }};
            font-size: 8.5px;
        }

        .summary-wrap {
            width: 100%;
            margin-top: 18px;
        }

        .summary {
            border:
                1px solid
                {{ $theme['border'] ?? '#D8E3EA' }};
            background:
                {{ $theme['surface'] ?? '#F5FAFC' }};
        }

        .summary td {
            padding: 6px 10px;
        }

        .summary-total td {
            padding: 10px;
            background:
                {{ $theme['primary'] ?? '#155E75' }};
            color: #fff;
            font-weight: bold;
            font-size: 12px;
        }

        .notes {
            margin-top: 18px;
            padding: 10px 12px;
            border-left:
                3px solid
                {{ $theme['accent'] ?? '#38BDF8' }};
            background:
                {{ $theme['surface'] ?? '#F5FAFC' }};
        }

        .signature {
            width: 40%;
            margin-left: auto;
            margin-top: 27px;
            text-align: center;
            page-break-inside: avoid;
        }

        .signature-space {
            height: 58px;
        }

        .signature-image {
            max-width: 145px;
            max-height: 58px;
        }

        .signature-name {
            font-weight: bold;
        }

        .footnote {
            margin-top: 24px;
            padding-top: 8px;
            border-top: 1px solid
                {{ $theme['border'] ?? '#D7DEE8' }};
            color:
                {{ $theme['muted'] ?? '#667085' }};
            font-size: 8px;
        }
    </style>
</head>

<body data-invoice-layout="ocean">

@if ($backgroundDataUri)
    <div class="page-background">
        <img
            src="{{ $backgroundDataUri }}"
            alt=""
        >
    </div>
@endif

<div class="page">

<table class="header">
    <tr>
        <td style="width: 58%;">
            @if ($showLogo)
                <img
                    src="{{ $branding['logo_data_uri'] }}"
                    class="logo"
                    alt="Logo usaha"
                >
            @endif

            <div class="business-name">
                {{ $branding['business_name'] }}
            </div>

            <div class="business-meta">
                @if ($branding['address'])
                    <div>{{ $branding['address'] }}</div>
                @endif

                @if ($branding['phone'])
                    <div>Telp: {{ $branding['phone'] }}</div>
                @endif

                @if ($branding['email'])
                    <div>Email: {{ $branding['email'] }}</div>
                @endif

                @if ($branding['tax_id'])
                    <div>
                        NPWP/ID Pajak:
                        {{ $branding['tax_id'] }}
                    </div>
                @endif
            </div>
        </td>

        <td style="width: 42%;">
            <div class="document-box">
                <div class="document-label">
                    Tagihan
                </div>

                <div class="document-number">
                    {{ $document['number'] }}
                </div>

                <div class="document-status">
                    <span class="status">
                        {{ $document['status_label'] }}
                    </span>
                </div>
            </div>
        </td>
    </tr>
</table>

<table style="margin-top: 18px;">
    <tr>
        <td style="width: 56%; vertical-align: top;">
            <div class="bill-to">
                <div class="label">
                    Ditagihkan Kepada
                </div>

                <div class="customer-name">
                    {{ $customer['name'] }}
                </div>
            </div>
        </td>

        <td style="width: 4%;"></td>

        <td style="width: 38%; vertical-align: top;">
            <table class="meta">
                <tr>
                    <td class="meta-label">
                        Status
                    </td>

                    <td class="text-right">
                        <span class="status">
                            {{ $document['status_label'] }}
                        </span>
                    </td>
                </tr>

                <tr>
                    <td class="meta-label">
                        Terbit
                    </td>

                    <td class="text-right">
                        {{ $document['issued_at'] }}
                    </td>
                </tr>

                <tr>
                    <td class="meta-label">
                        Jatuh Tempo
                    </td>

                    <td class="text-right">
                        {{ $document['due_at'] }}
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<table class="items">
    <thead>
        <tr>
            <th style="width: 5%;">No</th>
            <th>Item</th>
            <th style="width: 10%;">Qty</th>
            <th style="width: 12%;">Satuan</th>
            <th style="width: 17%;" class="text-right">
                Harga
            </th>
            <th style="width: 18%;" class="text-right">
                Jumlah
            </th>
        </tr>
    </thead>

    <tbody>
        @foreach ($items as $item)
            <tr>
                <td class="text-center">
                    {{ $item['number'] }}
                </td>

                <td>
                    <strong>
                        {{ $item['name'] }}
                    </strong>

                    @if ($item['description'])
                        <div class="muted">
                            {{ $item['description'] }}
                        </div>
                    @endif

                    @if (! empty($item['pricing_display']['formula']))
                        <div class="muted">
                            {{ $item['pricing_display']['formula'] }}
                        </div>

                        @if (! empty($item['pricing_display']['result_text']))
                            <div class="muted">
                                <strong>
                                    {{ $item['pricing_display']['result_text'] }}
                                </strong>
                            </div>
                        @endif
                    @endif
                </td>

                <td class="text-right">
                    {{ $item['quantity'] }}
                </td>

                <td>
                    {{ $item['unit'] }}
                </td>

                <td class="text-right">
                    {{ $item['unit_price'] }}
                </td>

                <td class="text-right">
                    {{ $item['amount'] }}
                </td>
            </tr>
        @endforeach
    </tbody>
</table>

<div class="summary-wrap">
    <table class="summary">
        <tr>
            <td style="width: 70%;">Subtotal</td>
            <td class="text-right">
                {{ $summary['subtotal'] }}
            </td>
        </tr>
        @if ($summary['show_global_discount'])
            <tr>
                <td>
                    {{ $summary['global_discount_label'] }}
                </td>

                <td class="text-right">
                    - {{ $summary['global_discount_amount'] }}
                </td>
            </tr>
        @endif

        @if ($summary['show_tax'])
            <tr>
                <td>
                    {{ $summary['tax_label'] }}
                </td>

                <td class="text-right">
                    {{ $summary['tax_total'] }}
                </td>
            </tr>
        @endif

        <tr class="summary-total">
            <td>Total Tagihan</td>

            <td class="text-right">
                {{ $document['currency'] }}
                {{ $summary['total'] }}
            </td>
        </tr>
    </table>
</div>

@if ($document['notes'])
    <div class="notes">
        <strong>Catatan</strong>

        <div>
            {!! nl2br(e($document['notes'])) !!}
        </div>
    </div>
@endif

@if (
    $branding['signature_name']
    || $branding['signature_title']
    || $branding['signature_image_data_uri']
)
    <div class="signature">
        <div>Hormat kami,</div>

        <div class="signature-space">
            @if ($branding['signature_image_data_uri'])
                <img
                    src="{{ $branding['signature_image_data_uri'] }}"
                    class="signature-image"
                    alt="Tanda tangan"
                >
            @endif
        </div>

        @if ($branding['signature_name'])
            <div class="signature-name">
                {{ $branding['signature_name'] }}
            </div>
        @endif

        @if ($branding['signature_title'])
            <div>
                {{ $branding['signature_title'] }}
            </div>
        @endif
    </div>
@endif

@if ($branding['invoice_footnote'])
    <div class="footnote">
        {!! nl2br(e(
            $branding['invoice_footnote']
        )) !!}
    </div>
@endif

</div>
</body>
</html>
