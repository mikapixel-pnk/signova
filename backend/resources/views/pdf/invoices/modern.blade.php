<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">

    <title>
        Tagihan {{ $document['number'] }}
    </title>

    <style>
        @page {
            margin: 28px 32px;
        }

        body {
            margin: 0;
            font-family: DejaVu Sans, sans-serif;
            font-size: 10.5px;
            line-height: 1.45;
            color: {{ $theme['text'] ?? '#1F2937' }};
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        .hero {
            background:
                {{ $theme['primary'] ?? '#059669' }};
            color: #fff;
            padding: 22px 24px;
            margin-bottom: 20px;
        }

        .hero-table td {
            vertical-align: top;
        }

        .brand {
            font-size: 20px;
            font-weight: bold;
        }

        .brand-meta {
            margin-top: 8px;
            font-size: 9px;
            line-height: 1.55;
        }

        .document-label {
            text-align: right;
            font-size: 24px;
            font-weight: bold;
            letter-spacing: 1px;
        }

        .document-number {
            text-align: right;
            margin-top: 6px;
            font-size: 11px;
        }

        .info-table {
            margin-bottom: 20px;
        }

        .customer-card {
            width: 58%;
            padding: 14px;
            background:
                {{ $theme['surface'] ?? '#F8FAFC' }};
            border-left: 4px solid
                {{ $theme['primary'] ?? '#059669' }};
        }

        .customer-label {
            color:
                {{ $theme['muted'] ?? '#6B7280' }};
            font-size: 9px;
            text-transform: uppercase;
            margin-bottom: 5px;
        }

        .customer-name {
            font-size: 14px;
            font-weight: bold;
        }

        .meta {
            width: 38%;
            margin-left: auto;
        }

        .meta td {
            padding: 3px 0;
        }

        .meta-label {
            color:
                {{ $theme['muted'] ?? '#6B7280' }};
        }

        .status {
            display: inline-block;
            padding: 3px 8px;
            background:
                {{ $theme['accent'] ?? '#D1FAE5' }};
            color:
                {{ $theme['primary_dark'] ?? '#047857' }};
            font-weight: bold;
        }

        .items {
            margin-top: 10px;
        }

        .items th {
            text-align: left;
            padding: 8px 6px;
            color:
                {{ $theme['primary_dark'] ?? '#047857' }};
            border-bottom: 2px solid
                {{ $theme['primary'] ?? '#059669' }};
            font-size: 9px;
            text-transform: uppercase;
        }

        .items td {
            padding: 9px 6px;
            border-bottom: 1px solid
                {{ $theme['border'] ?? '#D1D5DB' }};
            vertical-align: top;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .muted {
            color:
                {{ $theme['muted'] ?? '#6B7280' }};
            font-size: 9px;
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
            padding-top: 8px;
            border-top: 2px solid
                {{ $theme['primary'] ?? '#059669' }};
            font-weight: bold;
            font-size: 12px;
        }

        .notes {
            margin-top: 22px;
            padding: 12px;
            background:
                {{ $theme['surface'] ?? '#F8FAFC' }};
        }

        .signature {
            width: 42%;
            margin-left: auto;
            margin-top: 28px;
            text-align: center;
            page-break-inside: avoid;
        }

        .signature-space {
            height: 58px;
        }

        .signature-image {
            max-width: 150px;
            max-height: 58px;
        }

        .signature-name {
            font-weight: bold;
        }

        .footnote {
            margin-top: 24px;
            padding-top: 9px;
            border-top: 1px solid
                {{ $theme['border'] ?? '#D1D5DB' }};
            color:
                {{ $theme['muted'] ?? '#6B7280' }};
            font-size: 8.5px;
        }
    </style>
</head>

<body data-invoice-layout="modern">

<div class="hero">
    <table class="hero-table">
        <tr>
            <td style="width: 58%;">
                <div class="brand">
                    {{ $branding['business_name'] }}
                </div>

                <div class="brand-meta">
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
</div>

<table class="info-table">
    <tr>
        <td style="width: 60%; vertical-align: top;">
            <div class="customer-card">
                <div class="customer-label">
                    Ditagihkan Kepada
                </div>

                <div class="customer-name">
                    {{ $customer['name'] }}
                </div>
            </div>
        </td>

        <td style="width: 40%; vertical-align: top;">
            <table class="meta">
                <tr>
                    <td class="meta-label">Status</td>
                    <td class="text-right">
                        <span class="status">
                            {{ $document['status_label'] }}
                        </span>
                    </td>
                </tr>

                <tr>
                    <td class="meta-label">Terbit</td>
                    <td class="text-right">
                        {{ $document['issued_at'] }}
                    </td>
                </tr>

                <tr>
                    <td class="meta-label">Jatuh Tempo</td>
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
            <th style="width: 17%;" class="text-right">Harga</th>
            <th style="width: 18%;" class="text-right">Jumlah</th>
        </tr>
    </thead>

    <tbody>
        @foreach ($items as $item)
            <tr>
                <td class="text-center">
                    {{ $item['number'] }}
                </td>

                <td>
                    <strong>{{ $item['name'] }}</strong>

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
        {!! nl2br(e($branding['invoice_footnote'])) !!}
    </div>
@endif

</body>
</html>
