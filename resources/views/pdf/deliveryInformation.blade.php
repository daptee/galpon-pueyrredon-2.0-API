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

        .pedido {
            font-weight: bold;
            color: #8076F8;
            margin: 10px 35px;
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
            margin: 10px 35px;
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
                    <p style="margin: 2px 0; font-weight: bold;">Om3</p>
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
                    <p style="margin: 2px 0;"><strong>{{ $budget->client->name }}</strong></p>
                    <p style="margin: 2px 0;"><strong>{{ $budget->place->name }}</strong></p>
                </td>
                <td style="width: 14%; text-align: left; vertical-align: top;">
                    <p style="padding: 0 0 0 3px; margin: 2px 0;">Fecha y hora: </p>
                    <p style="padding: 0 0 0 3px; margin: 2px 0;">Evento: </p>
                </td>
                <td style="width: 10%; text-align: left; vertical-align: top;">
                    <p style="margin: 2px 0;">
                        <strong>{{ \Carbon\Carbon::parse($budget->date_event)->format('d-M-Y') }}</strong>
                    </p>
                    <p style="margin: 2px 0;"><strong>{{ $budget->days }} día/s</strong></p>
                </td>
            </tr>
        </table>
        <table style="width: 100%; margin-bottom: 10px;">
            <tr>
                <td style="width: 15%; vertical-align: top;">
                    <p style="margin: 2px 0;">Dirección: </p>
                    <p style="margin: 2px 0;">Entrega: </p>
                </td>
                <td style="width: 61%; vertical-align: top;">
                    <p style="margin: 2px 0;"><strong>{{ $budget->budgetDeliveryData->address }}</strong></p>
                    <p style="margin: 2px 0;"><strong>{{ \Carbon\Carbon::parse($budget->budgetDeliveryData->delivery_datetime)->format('d-M-Y') }}</strong></p>
                </td>
                <td style="width: 14%; text-align: left; vertical-align: top;">
                    <p style="padding: 0 0 0 3px; margin: 10px 0;"> </p>
                    <p style="padding: 0 0 0 3px; margin: 2px 0;">Retiro: </p>
                </td>
                <td style="width: 10%; text-align: left; vertical-align: top;">
                    <p style="padding: 0 0 0 3px; margin: 10px 0;"> </p>
                    <p style="margin: 2px 0;"><strong>{{ \Carbon\Carbon::parse($budget->budgetDeliveryData->widthdrawal_datetime)->format('d-M-Y') }}</strong></p>
                </td>
            </tr>
        </table>
    </div>


    <table class="table">
        <thead>
            <tr>
                <th>Cantidad</th>
                <th>Artículo</th>
            </tr>
        </thead>
        <tbody>
            @foreach($budget->budgetProducts as $item)
                <tr style="background-color: {{ $loop->iteration % 2 === 0 ? '#FFFFFF' : '#F6F6FF' }};">
                    <td style="width: 15%;">{{ $item->quantity }}</td>
                    <td>{{ $item->product->name }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <p class="pedido" style="font-size: 12px; font-weight: bold; color: #8076F8;">Detalle de pedido:</p>

    <table class="budget" style="width: 100%; border-collapse: collapse; background-color: rgb(255, 255, 255);">
        <tr>
            <td style="width: 50%;">
                Coordinación:&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                <strong>
                    {{ $budget->budgetDeliveryData->coordination_contact }}&nbsp;-&nbsp;{{ $budget->budgetDeliveryData->cellphone_coordination }}
                </strong>
            </td>
            <td style="width: 50%;">
                Opciones de entrega:&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                <strong>
                    {{ $budget->budgetDeliveryData->delivery_options }}
                </strong>
            </td>
        </tr>
        <tr>
            <td style="width: 50%;">
                Recepción:&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                <strong>
                    {{ $budget->budgetDeliveryData->reception_contact }}&nbsp;-&nbsp;{{ $budget->budgetDeliveryData->cellphone_reception }}
                </strong>
            </td>
            <td style="width: 50%;">
                Opciones de retiro:&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                <strong>
                    {{ $budget->budgetDeliveryData->widthdrawal_options }}
                </strong>
            </td>
        </tr>
    </table>

    <table class="budget" style="width: 100%; border-collapse: collapse; background-color: rgb(255, 255, 255);">
        
        <tr>
            <td style="width: 25%;">
                Distancia:&nbsp;&nbsp;&nbsp;&nbsp;
                <strong>
                    {{ $budget->place->distance }}
                </strong>
            </td>
            <td style="width: 25%;">
                Tiempo:&nbsp;&nbsp;&nbsp;&nbsp;
                <strong>
                    {{ $budget->place->travel_time }}min
                </strong>
            </td>
            <td style="width: 25%;">
                Armado:&nbsp;&nbsp;&nbsp;&nbsp;
                <strong>
                    {{ $budget->place->complexity_factor }}
                </strong>
            </td>
            <td style="width: 25%;">
                Total peajes:&nbsp;&nbsp;&nbsp;&nbsp;
                <strong>
                    
                </strong>
            </td>
        </tr>
    </table>

    <div class="budget">
        <p style="margin: 8px 0;">Detalles adicionales de entrega:&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
            <strong>
                {{ $budget->budgetDeliveryData->additional_delivery_details }}
            </strong> 
        </p>
        <p style="margin: 8px 0;">Detalles adicionales de pedido:&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
            <strong>
                {{ $budget->budgetDeliveryData->additional_order_details }}
            </strong>
        </p>
    </div>

</body>

</html>