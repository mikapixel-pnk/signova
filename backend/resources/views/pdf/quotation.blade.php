<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">

    <title>
        Penawaran {{ $quotation->quotation_number }}
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
        .summary-table {
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

        .footer {
            margin-top: 28px;
            border-top: 1px solid #ddd;
            padding-top: 10px;
            font-size: 9px;
            color: #666;
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
                PENAWARAN
            </div>

            <div class="document-number">
                {{ $quotation->quotation_number }}
            </div>
        </td>
    </tr>
</table>

<div class="section-title">
    Kepada
</div>

<div>
    <strong>
        {{ $quotation->customer->name }}
    </strong>
</div>

<table class="meta-table" style="margin-top: 12px;">
    <tr>
        <td style="width: 30%;">
            Status
        </td>
        <td>
            : {{ $quotation->status }}
        </td>
    </tr>

    <tr>
        <td>
            Berlaku Sampai
        </td>
        <td>
            :
            {{ $quotation->valid_until
                ? $quotation->valid_until->format('d-m-Y')
                : '-' }}
        </td>
    </tr>

    <tr>
        <td>
            Revisi
        </td>
        <td>
            : {{ $version->revision_no }}
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
    @foreach ($version->items as $index => $item)
        <tr>
            <td class="text-center">
                {{ $index + 1 }}
            </td>

            <td>
                <strong>
                    {{ $item->name }}
                </strong>

                @if ($item->description)
                    <div class="muted">
                        {{ $item->description }}
                    </div>
                @endif
            </td>

            <td class="text-right">
                {{ rtrim(
                    rtrim(
                        number_format(
                            (float) $item->quantity,
                            4,
                            ',',
                            '.'
                        ),
                        '0'
                    ),
                    ','
                ) }}
            </td>

            <td>
                {{ $item->unit_symbol
                    ?: $item->unit_name
                    ?: $item->unit_code
                    ?: '-' }}
            </td>

            <td class="text-right">
                {{ number_format(
                    (float) $item->unit_price,
                    2,
                    ',',
                    '.'
                ) }}
            </td>

            <td class="text-right">
                {{ number_format(
                    (float) $item->amount,
                    2,
                    ',',
                    '.'
                ) }}
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
                {{ number_format(
                    (float) $version->subtotal,
                    2,
                    ',',
                    '.'
                ) }}
            </td>
        </tr>

        <tr>
            <td>Diskon</td>
            <td class="text-right">
                {{ number_format(
                    (float) $version->discount_total,
                    2,
                    ',',
                    '.'
                ) }}
            </td>
        </tr>

        <tr>
            <td>Pajak</td>
            <td class="text-right">
                {{ number_format(
                    (float) $version->tax_total,
                    2,
                    ',',
                    '.'
                ) }}
            </td>
        </tr>

        <tr class="summary-total">
            <td>Total</td>
            <td class="text-right">
                {{ $version->currency }}
                {{ number_format(
                    (float) $version->total,
                    2,
                    ',',
                    '.'
                ) }}
            </td>
        </tr>
    </table>
</div>

@if ($version->terms)
    <div class="notes">
        <strong>Syarat & Ketentuan</strong>
        <div>
            {!! nl2br(e($version->terms)) !!}
        </div>
    </div>
@endif

@if ($version->notes)
    <div class="notes">
        <strong>Catatan</strong>
        <div>
            {!! nl2br(e($version->notes)) !!}
        </div>
    </div>
@endif

@if ($branding['quotation_footer'])
    <div class="footer">
        {!! nl2br(e(
            $branding['quotation_footer']
        )) !!}
    </div>
@endif

</body>
</html>
