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
            background-color: rgb(255, 0, 0);
        }

        body {
            font-family: 'Lato', sans-serif;
            font-size: 10px;
            color: #333;
            background-color: rgb(255, 255, 255);
        }

        .title {
            background-color: #F6F6FF;
            padding: 30px 35px;
        }


        .logo {
            font-size: 12px;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table th {
            background-color: rgb(255, 255, 255);
            font-size: 12px;
            color: #8076F8;
            text-align: left;
            padding: 6px 36px;
            border-bottom: 1px inset #E2E0FD;
        }

        .table td {
            padding: 6px 36px;
        }

        .budget {
            padding: 6px 16px;
        }

        .footer {
            background-color: rgb(255, 255, 255);
            font-size: 10px;
            color: #666;
            padding: 6px 36px;
        }

        .footer p {
            margin: 2px 0;
        }
    </style>
</head>

<body>
    <div class="title">
        <table style="width: 100%; margin-bottom: 10px;">
            <tr>
                <td class="logo" style="width: 76%; vertical-align: top;">
                    <img src="{{ public_path('images/logo.png') }}" style="height: 29px; margin: 7px 0;" alt="Logo">
                    <p style="margin: 0">galponpueyrredon@administrador.com - 15-5220-9988</p>
                </td>
                <td style="width: 14%; text-align: left; vertical-align: top;">
                    <p style="padding: 0 0 0 4px; margin: 0 0 2px 0;">Presupuesto: </p>
                    <p style="padding: 0 0 0 4px; margin: 2px 0;">Fecha: </p>
                    <p style="padding: 0 0 0 4px; margin: 2px 0;">Volumen: </p>
                </td>
                <td style="width: 10%; text-align: left; vertical-align: top;">
                    <p style="margin: 0 0 2px 0; font-weight: bold;">{{ str_pad($budget->id, 8, '0', STR_PAD_LEFT) }}
                    </p>
                    <p style="margin: 2px 0; font-weight: bold;">
                        {{ \Carbon\Carbon::parse($budget->date_event)->format('d-M-Y') }}
                    </p>
                    <p style="margin: 2px 0; font-weight: bold;">{{ number_format($budget->volume / 1000, 1) }}m<sup>3</sup></p>
                </td>
            </tr>
        </table>

        <table style="width: 100%; margin-bottom: 10px;">
            <tr>
                <td style="width: 6%; vertical-align: top;">
                    <p style="margin: 2px 0;">Cliente: </p>
                    <p style="margin: 2px 0;">Lugar: </p>
                </td>
                <td style="width: 71%; vertical-align: top;">
                    <p style="margin: 2px 0;"><strong>{{ $budget->client->name ?? $budget->client_name }}</strong></p>
                    <p style="margin: 2px 0;"><strong>{{ $budget->place->name }}</strong></p>
                </td>
                <td style="width: 14%; text-align: left; vertical-align: top;">
                    <p style="padding: 0 0 0 3px; margin: 2px 0;">Evento: </p>
                    <p style="padding: 0 0 0 3px; margin: 2px 0;">Periodo: </p>
                </td>
                <td style="width: 10%; text-align: left; vertical-align: top;">
                    <p style="margin: 2px 0;">
                        <strong>{{ \Carbon\Carbon::parse($budget->date_event)->format('d-M-Y') }}</strong>
                    </p>
                    <p style="margin: 2px 0;"><strong>{{ $budget->days }} día/s</strong></p>
                </td>
            </tr>
        </table>
    </div>


    <table class="table">
        <thead>
            <tr>
                <th>Cantidad</th>
                <th>Artículo</th>
                <th>Valor</th>
                <th>Días</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($budget->budgetProducts as $item)
                <tr style="background-color: {{ $loop->iteration % 2 === 0 ? '#FFFFFF' : '#F6F6FF' }};">
                    <td>{{ $item->quantity }}</td>
                    <td style="width: 35%;">{{ $item->product->name }}</td>
                    <td>${{ number_format($item->price, 2, ',', '.') }}</td>
                    <td style="width: 12%; text-align: left;">{{ $budget->quoted_days }}</td>
                    <td style="width: 18%; text-align: right; padding: 0 45px 0 0;">
                        ${{ number_format($item->price * $item->quantity, 2, ',', '.') }}
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="budget" style="width: 100%; border-collapse: collapse; background-color: rgb(255, 255, 255);">
        <tr>
            <td style="width: 80%; text-align: right; padding: 4px 8px;">Total Productos:</td>
            <td style="width: 20%; text-align: right; padding: 4px 29px 4px 8px; font-weight: bold;">
                ${{ number_format($budget->total_price_products, 2, ',', '.') }}</td>
        </tr>
        @if($budget->total_bonification && $budget->total_bonification != '0' && $budget->total_bonification != '0.00')
        <tr>
            <td style="width: 80%; text-align: right; padding: 4px 8px;">Total Bonificado:</td>
            <td
                style="width: 20%; text-align: right; padding: 4px 29px 4px 8px; color:rgb(255, 77, 77); font-weight: bold;">
                ${{ $budget->total_bonification }}</td>
        </tr>
        @endif
        <tr>
            <td style="width: 80%; text-align: right; padding: 4px 8px;">Traslados y armado:</td>
            <td style="width: 20%; text-align: right; padding: 4px 29px 4px 8px; font-weight: bold;">
                ${{ number_format($budget->transportation_cost_edited > 0 ? $budget->transportation_cost_edited : $budget->transportation_cost, 2, ',', '.') }}
            </td>
        </tr>
        <tr>
            <td style="width: 80%; text-align: right; padding: 4px 8px;">Subtotal:</td>
            <td style="width: 20%; text-align: right; padding: 4px 29px 4px 8px; font-weight: bold;">
                ${{ number_format($budget->subtotal, 2, ',', '.') }}</td>
        </tr>
        <tr>
            <td style="width: 80%; text-align: right; padding: 4px 8px;">IVA:</td>
            <td style="width: 20%; text-align: right; padding: 4px 29px 4px 8px; font-weight: bold;">
                ${{ number_format($budget->iva, 2, ',', '.') }}</td>
        </tr>
        <tr>
            <td style="width: 80%; text-align: right; padding: 4px 8px; color: #8076F8;">TOTAL:</td>
            <td style="width: 20%; text-align: right; padding: 4px 29px 4px 8px; font-weight: bold;">
                ${{ number_format($budget->total, 2, ',', '.') }}</td>
        </tr>
    </table>

    <div class="footer">
        <p><strong>Formas de pago:</strong> 50% al momento de reserva y saldo contra entrega. Transferencia bancaria.
            CBU: 015092090100011447638 CUIT: 20-202022285-6.</p>
        <p><strong>Depósito en garantía:</strong> Cheque o efectivo contra entrega (30%).</p>
        <p>El presupuesto se actualiza según IPC mensual (ajuste entre día 5 y 10 de cada mes).</p>
        <p><strong>Validez del presupuesto:</strong> 10 días.</p>
        <p><strong>Advertencia:</strong> Cualquier daño, mancha o pérdida será evaluada. El mobiliario no puede estar a
            la intemperie o en condiciones climáticas extremas.</p>
    </div>
</body>

</html>