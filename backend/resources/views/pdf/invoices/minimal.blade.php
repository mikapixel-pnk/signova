<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">

    <title>
        Tagihan {{ $document['number'] }}
    </title>

    <style>
        @page {
            margin: 38px 40px;
        }

        body {
            margin: 0;
            font-family: DejaVu Sans, sans-serif;
            font-size: 10.5px;
            line-height: 1.55;
            color:
                {{ $theme['text'] ?? '#0F172A' }};
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        .top td {
            vertical-align: top;
        }

        .brand {
            font-size: 17px;
            font-weight: bold;
        }

        .brand-meta {
            margin-top: 7px;
            color:
                {{ $theme['muted'] ?? '#64748B' }};
            font-size: 9px;
        }

        .title {
            text-align: right;
            font-size: 22px;
            letter-spacing: 2px;
            font-weight: normal;
        }

        .number {
            text-align: right;
            margin-top: 5px;
            color:
                {{ $theme['muted'] ?? '#64748B' }};
        }

        .divider {
            margin: 22px 0;
            border-top: 1px solid
                {{ $theme['border'] ?? '#CBD5E1' }};
        }

        .section-label {
            font-size: 8.5px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color:
                {{ $theme['muted'] ?? '#64748B' }};
        }

        .customer-name {
            margin-top: 5px;
            font-size: 14px;
            font-weight: bold;
        }

        .meta td {
            padding: 2px 0;
        }

        .meta-label {
            color:
                {{ $theme['muted'] ?? '#64748B' }};
        }

        .items {
            margin-top: 25px;
        }

        .items th {
            padding: 7px 4px;
            text-align: left;
            font-size: 8.5px;
            font-weight: normal;
            text-transform: uppercase;
            letter-spacing: .5px;
            color:
                {{ $theme['muted'] ?? '#64748B' }};
            border-bottom: 1px solid
                {{ $theme['primary'] ?? '#475569' }};
        }

        .items td {
            padding: 10px 4px;
            vertical-align: top;
            border-bottom: 1px solid
                {{ $theme['border'] ?? '#CBD5E1' }};
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .description {
            color:
                {{ $theme['muted'] ?? '#64748B' }};
            font-size: 9px;
            margin-top: 2px;
        }

        .summary-wrap {
            width: 40%;
            margin-left: auto;
            margin-top: 22px;
        }

        .summary td {
            padding: 4px 0;
        }

        .summary-total td {
            padding-top: 9px;
            border-top: 1px solid
                {{ $theme['primary'] ?? '#475569' }};
            font-size: 12px;
            font-weight: bold;
        }

        .notes {
            margin-top: 28px;
        }

        .signature {
            width: 38%;
            margin-left: auto;
            margin-top: 35px;
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
            border-top: 1px solid
                {{ $theme['border'] ?? '#CBD5E1' }};
            padding-top: 5px;
            font-weight: bold;
        }

        .footnote {
            margin-top: 32px;
            font-size: 8.5px;
            color:
                {{ $theme['muted'] ?? '#64748B' }};
        }
    </style>
</head>

<body data-invoice-layout="minimal">

<table class="top">
    <tr>
        <td style="width: 60%;">
            <div class="brand">
                {{ $branding['business_name'] }}
            </div>

            <div class="brand-meta">
                @if ($branding['address'])
                    <div>{{ $branding['address'] }}</div>
                @endif

                @if ($branding['phone'])
                    <div>{{ $branding['phone'] }}</div>
                @endif

                @if ($branding['email'])
                    <div>{{ $branding['email'] }}</div>
                @endif

                @if ($branding['tax_id'])
                    <div>
                        NPWP/ID Pajak:
                        {{ $branding['tax_id'] }}
                    </div>
                @endif
            </div>
        </td>

        <td style="width: 40%;">
            <div class="title">
                TAGIHAN
            </div>

            <div class="number">
                {{ $document['number'] }}
            </div>
        </td>
    </tr>
</table>

<div class="divider"></div>

<table>
    <tr>
        <td style="width: 58%; vertical-align: top;">
            <div class="section-label">
                Kepada
            </div>

            <div class="customer-name">
                {{ $customer['name'] }}
            </div>
        </td>

        <td style="width: 42%; vertical-align: top;">
            <table class="meta">
                <tr>
                    <td class="meta-label">Status</td>
                    <td class="text-right">
                        {{ $document['status_label'] }}
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
                        <div class="description">
                            {{ $item['description'] }}
                        </div>
                    @endif

                    @if (! empty($item['pricing_display']['formula']))
                        <div class="description">
                            {{ $item['pricing_display']['formula'] }}
                        </div>

                        @if (! empty($item['pricing_display']['result_text']))
                            <div class="description">
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

                <td>{{ $item['unit'] }}</td>

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
        <div class="section-label">
            Catatan
        </div>

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
