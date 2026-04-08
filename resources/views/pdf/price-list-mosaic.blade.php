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
            padding: 18px 24px 12px 24px;
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
            border-spacing: 10px;
            padding: 6px 14px;
        }

        .grid-table td {
            width: 25%;
            vertical-align: top;
            padding: 0;
        }

        .grid-table td.empty {
            border: none;
        }

        .card {
            border: 1px solid #E2E0FD;
            border-radius: 4px;
            overflow: hidden;
            page-break-inside: avoid;
        }

        .card-image {
            width: 100%;
            height: 130px;
            background-color: #EBEBF5;
            overflow: hidden;
            text-align: center;
        }

        .card-image img {
            width: 100%;
            height: 130px;
            object-fit: cover;
        }

        .card-body {
            padding: 6px 8px;
            background-color: #fff;
        }

        .card-name {
            font-weight: bold;
            font-size: 9px;
            margin: 0 0 3px 0;
            color: #333;
        }

        .card-meta {
            font-size: 8px;
            color: #666;
            margin: 0 0 1px 0;
        }

        .card-price {
            font-size: 10px;
            font-weight: bold;
            color: #8076F8;
            margin: 4px 0 0 0;
        }

        .footer {
            font-size: 8px;
            color: #999;
            padding: 6px 24px;
            border-top: 1px solid #E2E0FD;
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
                                @if($product->mainImage && $product->mainImage->image)
                                    <img src="{{ public_path('storage/product/img/' . $product->mainImage->image) }}" alt="{{ $product->name }}">
                                @else
                                    <table style="width:100%; height:130px;">
                                        <tr><td style="text-align:center; vertical-align:middle; color:#BBBBD0; font-size:8px;">Sin imagen</td></tr>
                                    </table>
                                @endif
                            </div>
                            <div class="card-body">
                                <p class="card-name">{{ $product->name }}</p>
                                <p class="card-meta">{{ $product->productLine->name ?? '—' }} &bull; {{ $product->productFurniture->name ?? '—' }}</p>
                                @if($product->attr_dimension || $product->attr_height)
                                    <p class="card-meta">
                                        @if($product->attr_dimension)Dim: {{ $product->attr_dimension }}@endif
                                        @if($product->attr_dimension && $product->attr_height) &nbsp;@endif
                                        @if($product->attr_height)Alt: {{ $product->attr_height }}@endif
                                    </p>
                                @endif
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
                {{-- Rellenar celdas vacías si la última fila tiene menos de 4 --}}
                @for($i = $row->count(); $i < 4; $i++)
                    <td class="empty"></td>
                @endfor
            </tr>
        @endforeach
    </table>

    <div class="footer">
        <p>Precios vigentes al {{ $generatedAt }}. Sujetos a modificación sin previo aviso.</p>
    </div>
</body>

</html>
