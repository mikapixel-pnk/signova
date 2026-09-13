<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">

    <title>
        Tagihan {{ $document['number'] }}
    </title>

    <style>
        @page {
            margin: 32px 34px;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            color: #222;
            line-height: 1.45;
        }

        .header-table,
        .meta-table,
        .items-table,
        .summary-table,
        .signature-table {
            width: 100%;
            border-collapse: collapse;
        }

        .brand {
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 4px;
        }

        .muted {
            color: #666;
        }

        .document-title {
            text-align: right;
            font-size: 22px;
            font-weight: bold;
        }

        .document-number {
            text-align: right;
            margin-top: 4px;
        }

        .section-title {
            font-weight: bold;
            margin-top: 20px;
            margin-bottom: 6px;
        }

        .meta-table td {
            padding: 2px 0;
            vertical-align: top;
        }

        .items-table {
            margin-top: 12px;
        }

        .items-table th {
            background: #f1f1f1;
            border: 1px solid #ddd;
            padding: 7px 6px;
            font-size: 10px;
        }

        .items-table td {
            border: 1px solid #ddd;
            padding: 7px 6px;
            vertical-align: top;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .summary-wrap {
            margin-top: 14px;
            width: 42%;
            margin-left: auto;
        }

        .summary-table td {
            padding: 4px 0;
        }

        .summary-total td {
            border-top: 1px solid #222;
            padding-top: 7px;
            font-weight: bold;
            font-size: 12px;
        }

        .notes {
            margin-top: 18px;
        }

        .signature-wrap {
            margin-top: 28px;
            page-break-inside: avoid;
        }

        .signature-box {
            width: 42%;
            margin-left: auto;
            text-align: center;
        }

        .signature-space {
            height: 58px;
        }

        .signature-image {
            display: block;
            max-width: 150px;
            max-height: 58px;
            margin: 0 auto;
        }

        .signature-name {
            font-weight: bold;
            text-decoration: underline;
        }

        .signature-title {
            margin-top: 2px;
        }

        .footnote {
            margin-top: 28px;
            border-top: 1px solid #ddd;
            padding-top: 10px;
            font-size: 9px;
            color: #666;
            page-break-inside: avoid;
        }
    </style>
</head>

<body>

<table class="header-table">
    <tr>
        <td style="width: 58%; vertical-align: top;">
            <div class="brand">
                {{ $branding['business_name'] }}
            </div>

            @if ($branding['address'])
                <div>
                    {{ $branding['address'] }}
                </div>
            @endif

            @if ($branding['phone'])
                <div>
                    Telp: {{ $branding['phone'] }}
                </div>
            @endif

            @if ($branding['email'])
                <div>
                    Email: {{ $branding['email'] }}
                </div>
            @endif

            @if ($branding['tax_id'])
                <div>
                    NPWP/ID Pajak:
                    {{ $branding['tax_id'] }}
                </div>
            @endif
        </td>

        <td style="width: 42%; vertical-align: top;">
            <div class="document-title">
                TAGIHAN
            </div>

            <div class="document-number">
                {{ $document['number'] }}
            </div>
        </td>
    </tr>
</table>

<div class="section-title">
    Kepada
</div>

<div>
    <strong>
        {{ $customer['name'] }}
    </strong>
</div>

<table class="meta-table" style="margin-top: 12px;">
    <tr>
        <td style="width: 30%;">
            Status
        </td>

        <td>
            :
            {{ $document['status_label'] }}
        </td>
    </tr>

    <tr>
        <td>
            Tanggal Terbit
        </td>

        <td>
            :
            {{ $document['issued_at'] }}
        </td>
    </tr>

    <tr>
        <td>
            Jatuh Tempo
        </td>

        <td>
            :
            {{ $document['due_at'] }}
        </td>
    </tr>
</table>

<table class="items-table">
    <thead>
        <tr>
            <th style="width: 5%;">No</th>
            <th>Item</th>
            <th style="width: 11%;">Qty</th>
            <th style="width: 12%;">Satuan</th>
            <th style="width: 17%;">Harga</th>
            <th style="width: 18%;">Jumlah</th>
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
    <table class="summary-table">
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
    <div class="signature-wrap">
        <div class="signature-box">
            <div>
                Hormat kami,
            </div>

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
                <div class="signature-title">
                    {{ $branding['signature_title'] }}
                </div>
            @endif
        </div>
    </div>
@endif

@if ($branding['invoice_footnote'])
    <div class="footnote">
        {!! nl2br(e(
            $branding['invoice_footnote']
        )) !!}
    </div>
@endif

</body>
</html>
