<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <style>
        @font-face {
            font-family: 'Lato';
            src: url('{{ public_path('fonts/Lato-Regular.ttf') }}') format('truetype');
            font-weight: normal;
            font-style: normal;
        }

        @font-face {
            font-family: 'Lato';
            src: url('{{ public_path('fonts/Lato-Bold.ttf') }}') format('truetype');
            font-weight: bold;
            font-style: normal;
        }

        @page {
            margin: 0 !important;
            padding: 0 !important;
        }

        body {
            font-family: 'Lato', sans-serif;
            font-size: 9px;
            color: #333;
            background-color: #fff;
        }

        .header {
            background-color: #F6F6FF;
            padding: 14px 20px 10px 20px;
        }

        .header-inner {
            width: 100%;
        }

        .header-inner td {
            vertical-align: middle;
        }

        .title-text {
            font-size: 13px;
            font-weight: bold;
            color: #8076F8;
            margin: 0 0 2px 0;
        }

        .subtitle-text {
            font-size: 9px;
            color: #666;
            margin: 0;
        }

        .grid-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 8px;
            padding: 4px 12px;
            table-layout: fixed;
        }

        .grid-table td {
            vertical-align: top;
            padding: 0;
        }

        .grid-table td.empty {
            border: none;
        }

        /* Modo normal: 4 cols x 3 filas */
        .mode-normal .grid-table td { width: 25%; }
        .mode-normal .card           { height: 164px; }
        .mode-normal .card-image     { height: 100px; }
        .mode-normal .card-image img { max-height: 100px; }
        .mode-normal .card-image table { height: 100px; }
        .mode-normal .card-body      { height: 64px; }
        .mode-normal .card-name      { font-size: 8px; }
        .mode-normal .card-meta      { font-size: 7px; }
        .mode-normal .card-price     { font-size: 9px; margin-top: -4px !important; }

        /* Modo compacto: 5 cols x 4 filas */
        .mode-compact .grid-table td { width: 20%; }
        .mode-compact .card           { height: 122px; }
        .mode-compact .card-image     { height: 70px; }
        .mode-compact .card-image img { max-height: 70px; }
        .mode-compact .card-image table { height: 70px; }
        .mode-compact .card-body      { height: 52px; padding: 3px 5px; }
        .mode-compact .card-name      { font-size: 7px; margin: 0 0 1px 0; }
        .mode-compact .card-meta      { font-size: 6px; margin: 0; }
        .mode-compact .card-price     { font-size: 8px; margin: 2px 0 0 0; }

        .card {
            border: 1px solid #E2E0FD;
            border-radius: 4px;
            overflow: hidden;
            page-break-inside: avoid;
        }

        .card-image {
            width: 100%;
            background-color: #EBEBF5;
        }

        .card-body {
            background-color: #fff;
            overflow: hidden;
            padding: 2px 5px 0 5px;
        }

        .card-name {
            font-weight: bold;
            color: #333;
            margin: 0 0 1px 0 !important;
        }

        .card-meta {
            color: #666;
            margin: 0 !important;
            line-height: 1.1;
        }

        .card-price {
            font-weight: bold;
            color: #8076F8;
            margin: 0 !important;
            line-height: 1.1;
        }
    </style>
</head>

@php
    $total   = $products->count();
    $compact = $total > 24;
    $perRow  = $compact ? 5 : 4;
    $chunks  = $products->chunk($perRow);
@endphp

<body class="{{ $compact ? 'mode-compact' : 'mode-normal' }}">
    <div class="header">
        <table class="header-inner">
            <tr>
                <td style="width: 60%;">
                    <img src="{{ public_path('images/logo.png') }}" style="height: 26px; margin-bottom: 4px;" alt="Logo">
                    <p class="subtitle-text">galponpueyrredon@administrador.com &mdash; 15-5220-9988</p>
                </td>
                <td style="width: 40%; text-align: right;">
                    <p class="title-text">Lista de Precios</p>
                    <p class="subtitle-text">Generado: {{ $generatedAt }}</p>
                </td>
            </tr>
        </table>
    </div>

    <table class="grid-table">
        @foreach($chunks as $row)
            <tr>
                @foreach($row as $product)
                    <td>
                        <div class="card">
                            <div class="card-image">
                                <table style="width:100%; background-color:#EBEBF5;">
                                    <tr>
                                        <td style="text-align:center; vertical-align:middle;">
                                            @if($product->mainImage && $product->mainImage->image)
                                                <img src="{{ public_path('storage/product/img/' . $product->mainImage->image) }}" alt="{{ $product->name }}" style="max-width:100%;">
                                            @else
                                                <span style="color:#BBBBD0; font-size:7px;">Sin imagen</span>
                                            @endif
                                        </td>
                                    </tr>
                                </table>
                            </div>
                            <div class="card-body">
                                <p class="card-name">{{ $product->name }}</p>
                                <p class="card-meta">{{ $product->productLine->name ?? '—' }} &bull; {{ $product->productFurniture->name ?? '—' }}</p>
                                <p class="card-meta">Dim: {{ $product->attr_dimension ?? '—' }} &nbsp; Alt: {{ $product->attr_height ?? '—' }}</p>
                                <p class="card-meta">Stock: {{ $product->stock ?? '—' }}</p>
                                @if($showPrices)
                                <p class="card-price" style="text-align: right;">
                                    @if($product->current_price !== null)
                                        ${{ number_format($product->current_price, 2, ',', '.') }}
                                    @else
                                        Sin precio
                                    @endif
                                </p>
                                @endif
                            </div>
                        </div>
                    </td>
                @endforeach
                @for($i = $row->count(); $i < $perRow; $i++)
                    <td class="empty"></td>
                @endfor
            </tr>
        @endforeach
        <tr>
            <td colspan="{{ $perRow }}" style="padding: 4px 0 0 0; border-top: 1px solid #E2E0FD;">
                <p style="font-size:8px; color:#999; margin:0;">Precios vigentes al {{ $generatedAt }}. Sujetos a modificación sin previo aviso.</p>
            </td>
        </tr>
    </table>
</body>

</html>
