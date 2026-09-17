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
                27mm
                17mm
                22mm
                17mm;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        .header td {
            vertical-align: top;
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
            font-size: 17px;
            font-weight: bold;
            color:
                {{ $theme['primary_dark'] ?? '#09192F' }};
        }

        .business-meta {
            margin-top: 5px;
            color:
                {{ $theme['muted'] ?? '#667085' }};
            font-size: 8.7px;
            line-height: 1.5;
        }

        .document-label {
            text-align: right;
            color:
                {{ $theme['primary'] ?? '#0F2747' }};
            font-size: 26px;
            font-weight: bold;
            letter-spacing: 1.2px;
        }

        .document-number {
            text-align: right;
            margin-top: 4px;
            color:
                {{ $theme['muted'] ?? '#667085' }};
            font-size: 10px;
        }

        .accent-line {
            height: 3px;
            margin: 18px 0 20px;
            background:
                {{ $theme['accent'] ?? '#2EA8FF' }};
        }

        .bill-to {
            background:
                {{ $theme['surface'] ?? '#F8FAFC' }};
            border-left: 4px solid
                {{ $theme['accent'] ?? '#2EA8FF' }};
            padding: 12px 14px;
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
                {{ $theme['surface'] ?? '#F8FAFC' }};
            color:
                {{ $theme['primary'] ?? '#0F2747' }};
            font-weight: bold;
        }

        .items {
            margin-top: 20px;
        }

        .items th {
            padding: 8px 6px;
            color: #fff;
            background:
                {{ $theme['primary'] ?? '#0F2747' }};
            font-size: 8.5px;
            text-transform: uppercase;
            letter-spacing: .4px;
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
            width: 44%;
            margin-left: auto;
            margin-top: 18px;
        }

        .summary td {
            padding: 4px 0;
        }

        .summary-total td {
            padding-top: 9px;
            border-top: 2px solid
                {{ $theme['accent'] ?? '#2EA8FF' }};
            color:
                {{ $theme['primary_dark'] ?? '#09192F' }};
            font-weight: bold;
            font-size: 12px;
        }

        .notes {
            margin-top: 20px;
            padding: 10px 12px;
            background:
                {{ $theme['surface'] ?? '#F8FAFC' }};
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

<body data-invoice-layout="premium">

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
            <div class="document-label">
                TAGIHAN
            </div>

            <div class="document-number">
                {{ $document['number'] }}
            </div>
        </td>
    </tr>
</table>

<div class="accent-line"></div>

<table>
    <tr>
        <td style="width: 58%; vertical-align: top;">
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
            <td>Subtotal</td>
            <td class="text-right">
                {{ $summary['subtotal'] }}
            </td>
        </tr>

        <tr>
            <td>Diskon</td>
            <td class="text-right">
                {{ $summary['discount_total'] }}
            </td>
        </tr>

        <tr>
            <td>Pajak</td>
            <td class="text-right">
                {{ $summary['tax_total'] }}
            </td>
        </tr>

        <tr class="summary-total">
            <td>Total</td>

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
