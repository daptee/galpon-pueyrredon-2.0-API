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
            padding: 18px 30px 12px 30px;
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

        .table-wrap {
            padding: 0 24px;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 0;
        }

        .table th {
            background-color: #fff;
            font-size: 9px;
            color: #8076F8;
            text-align: left;
            padding: 5px 10px;
            border-bottom: 1px solid #E2E0FD;
        }

        .table th.right {
            text-align: right;
        }

        .table td {
            padding: 4px 10px;
            font-size: 9px;
        }

        .table td.right {
            text-align: right;
        }

        .footer {
            font-size: 8px;
            color: #999;
            padding: 6px 30px;
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

    <div class="table-wrap">
    <table class="table">
        <thead>
            <tr>
                <th>Producto</th>
                <th>Línea</th>
                <th>Tipo de Mueble</th>
                <th>Stock</th>
                <th>Dimensiones</th>
                <th>Altura</th>
                <th class="right">Precio</th>
            </tr>
        </thead>
        <tbody>
            @foreach($products as $product)
                <tr style="background-color: {{ $loop->iteration % 2 === 0 ? '#FFFFFF' : '#F6F6FF' }};">
                    <td>{{ $product->name }}</td>
                    <td>{{ $product->productLine->name ?? '—' }}</td>
                    <td>{{ $product->productFurniture->name ?? '—' }}</td>
                    <td>{{ $product->stock ?? '—' }}</td>
                    <td>{{ $product->attr_dimension ?? '—' }}</td>
                    <td>{{ $product->attr_height ?? '—' }}</td>
                    <td class="right">
                        @if($product->current_price !== null)
                            ${{ number_format($product->current_price, 2, ',', '.') }}
                        @else
                            —
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
    </div>

    <div class="footer">
        <p>Precios vigentes al {{ $generatedAt }}. Sujetos a modificación sin previo aviso.</p>
    </div>
</body>

</html>
