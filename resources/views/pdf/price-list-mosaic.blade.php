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

        /* Tabla principal: 4 columnas, spacing entre celdas */
        .grid-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 8px;
            padding: 4px 12px;
            table-layout: fixed;
        }

        .grid-table td {
            width: 25%;
            vertical-align: top;
            padding: 0;
        }

        .grid-table td.empty {
            border: none;
        }

        /* Card: altura fija total = imagen (100px) + body (62px) = 162px */
        .card {
            border: 1px solid #E2E0FD;
            border-radius: 4px;
            overflow: hidden;
            height: 162px;
            page-break-inside: avoid;
        }

        /* Imagen fija en 100px, fondo gris para el espacio sobrante */
        .card-image {
            width: 100%;
            height: 100px;
            background-color: #EBEBF5;
        }

        /* Body fijo en 62px */
        .card-body {
            height: 62px;
            padding: 5px 7px;
            background-color: #fff;
            overflow: hidden;
        }

        .card-name {
            font-weight: bold;
            font-size: 8px;
            margin: 0 0 2px 0;
            color: #333;
        }

        .card-meta {
            font-size: 7px;
            color: #666;
            margin: 0 0 1px 0;
        }

        .card-price {
            font-size: 9px;
            font-weight: bold;
            color: #8076F8;
            margin: 3px 0 0 0;
        }
    </style>
</head>

<body>
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

    @php $chunks = $products->chunk(4); @endphp

    <table class="grid-table">
        @foreach($chunks as $row)
            <tr>
                @foreach($row as $product)
                    <td>
                        <div class="card">
                            <div class="card-image">
                                <table style="width:100%; height:100px; background-color:#EBEBF5;">
                                    <tr>
                                        <td style="text-align:center; vertical-align:middle;">
                                            @if($product->mainImage && $product->mainImage->image)
                                                <img src="{{ public_path('storage/product/img/' . $product->mainImage->image) }}" alt="{{ $product->name }}" style="max-width:100%; max-height:100px;">
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
                                <p class="card-meta">
                                    Dim: {{ $product->attr_dimension ?? '—' }} &nbsp; Alt: {{ $product->attr_height ?? '—' }}
                                </p>
                                <p class="card-meta">Stock: {{ $product->stock ?? '—' }}</p>
                                <p class="card-price">
                                    @if($product->current_price !== null)
                                        ${{ number_format($product->current_price, 2, ',', '.') }}
                                    @else
                                        Sin precio
                                    @endif
                                </p>
                            </div>
                        </div>
                    </td>
                @endforeach
                @for($i = $row->count(); $i < 4; $i++)
                    <td class="empty"></td>
                @endfor
            </tr>
        @endforeach
        <tr>
            <td colspan="4" style="padding: 4px 0 0 0; border-top: 1px solid #E2E0FD;">
                <p style="font-size:8px; color:#999; margin:0;">Precios vigentes al {{ $generatedAt }}. Sujetos a modificación sin previo aviso.</p>
            </td>
        </tr>
    </table>
</body>

</html>
