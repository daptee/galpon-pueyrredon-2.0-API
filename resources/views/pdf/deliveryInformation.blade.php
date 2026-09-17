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
            font-size: 11px;
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
            font-size: 11px;
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
                <td class="logo" style="width: 65%; vertical-align: top;">
                    <div>
                        <img src="{{ public_path('images/logo.png') }}" style="height: 29px; vertical-align: bottom;"
                            alt="Logo">
                        <span
                            style="font-weight: bold; font-size: 24px; vertical-align: top; margin: 0; padding: 0; line-height: 1;">
                            - FICHA LOGÍSTICA
                        </span>
                    </div>
                    <p style="margin: 0">galponpueyrredon@hotmail.com</p>
                </td>
                <td style="width: 10%; text-align: left; vertical-align: top;">
                    <p style="padding: 0 0 0 4px; margin: 2px 0 2px 0;">Presupuesto: </p>
                    <p style="padding: 0 0 0 4px; margin: 2px 0;">Volumen: </p>
                    <p style="padding: 0 0 0 4px; margin: 2px 0;">Ficha logística: </p>
                </td>
                <td style="width: 14%; text-align: left; vertical-align: top;">
                    <p style="margin: 2px 0 2px 0; font-weight: bold;">{{ str_pad($budget->id, 8, '0', STR_PAD_LEFT) }}
                    </p>
                    <p style="margin: 2px 0; font-weight: bold;">{{ number_format($budget->volume / 1000, 1) }}m<sup>3</sup></p>
                    <p style="margin: 2px 0; font-weight: bold;">
                        {{ ($budget->logisticsSheet->is_completed ?? false) ? 'Completa' : 'Incompleta' }}
                        {{ ($budget->logisticsSheet->budget_ratified ?? false) ? '(ratificada)' : '' }}
                    </p>
                </td>
            </tr>
        </table>

        <table style="width: 100%; margin-bottom: 10px;">
            <tr>
                <td style="width: 6%; vertical-align: top;">
                    <p style="margin: 2px 0;">Cliente: </p>
                    <p style="margin: 2px 0;">Lugar: </p>
                    <p style="margin: 2px 0;">Tipo de evento: </p>
                </td>
                <td style="width: 60%; vertical-align: top;">
                    <p style="margin: 2px 0;"><strong>{{ $budget->client->name ?? $budget->client_mail }}</strong></p>
                    <p style="margin: 2px 0;"><strong>{{ $budget->place->name }}</strong></p>
                    <p style="margin: 2px 0;">
                        <strong>{{ $budget->budgetDeliveryData->eventType->name ?? ($budget->logisticsSheet->event_type_other ?? "") }}</strong>
                    </p>
                </td>
                <td style="width: 10%; text-align: left; vertical-align: top;">
                    <p style="padding: 0 0 0 2px; margin: 2px 0;">Inicio: </p>
                    <p style="padding: 0 0 0 2px; margin: 2px 0;">Fin: </p>
                    <p style="padding: 0 0 0 2px; margin: 2px 0;">Duración: </p>
                </td>
                <td style="width: 14%; text-align: left; vertical-align: top;">
                    <p style="margin: 2px 0;">
                        <strong>{{ \Carbon\Carbon::parse($budget->date_event)->format('d-M-Y') }} -
                            {{ $budget->budgetDeliveryData->event_time ?? "" }}</strong>
                    </p>
                    <p style="margin: 2px 0;">
                        <strong>{{ optional($budget->logisticsSheet->event_end_datetime ?? null)->format('d-M-Y H:i') ?? "" }}</strong>
                    </p>
                    <p style="margin: 2px 0;"><strong>{{ $budget->days }} día/s</strong></p>
                </td>
            </tr>
        </table>
        <table style="width: 100%; margin-bottom: 10px;">
            <tr>
                <td style="width: 15%; vertical-align: top;">
                    <p style="margin: 2px 0;">Dirección: </p>
                    <p style="margin: 2px 0;">Accesibilidad: </p>
                    <p style="margin: 2px 0;">Opciones de entrega: </p>
                    <p style="margin: 2px 0;">Opciones de retiro: </p>
                </td>
                <td style="width: 61%; vertical-align: top;">
                    <p style="margin: 2px 0;">
                        <strong>{{ $budget->budgetDeliveryData->address ?? "" }}</strong>
                        @if($budget->logisticsSheet->address_maps_link ?? null)
                            &nbsp;-&nbsp;<a href="{{ $budget->logisticsSheet->address_maps_link }}">Ver en Maps</a>
                        @endif
                    </p>
                    <p style="margin: 2px 0;"><strong>{{ $budget->logisticsSheet->accessibility_comments ?? "" }}</strong></p>
                    <p style="margin: 2px 0;"><strong>{{ $budget->budgetDeliveryData->delivery_options ?? "" }}</strong></p>
                    <p style="margin: 2px 0;"><strong>{{ $budget->budgetDeliveryData->widthdrawal_options ?? "" }}</strong>
                    </p>
                </td>
            </tr>
        </table>
    </div>


    <table class="table">
        <thead>
            <tr>
                <th>Cantidad</th>
                <th>Artículo</th>
                <th>Componentes</th>
            </tr>
        </thead>
        <tbody>
            @php $rowIndex = 0; @endphp
            @foreach($budget->budgetProducts as $item)
                @if($item->product->id_product_type == 2 && $item->product->comboItems->count() > 0)
                    {{-- Producto combo: expandir componentes --}}
                    @foreach($item->product->comboItems as $comboItem)
                        @php $rowIndex++; @endphp
                        <tr style="background-color: {{ $rowIndex % 2 === 0 ? '#FFFFFF' : '#F6F6FF' }};">
                            <td style="width: 15%;">{{ $item->quantity * $comboItem->quantity }}</td>
                            <td>{{ $comboItem->product->name }}</td>
                            <td>
                                {{ $comboItem->product->attributeValues
                                ->filter(fn($attr) => isset($attr->attribute->id) && $attr->attribute->id == 5)
                                ->pluck('value')
                                ->implode(', ') }}
                            </td>
                        </tr>
                    @endforeach
                @else
                    {{-- Producto normal --}}
                    @php $rowIndex++; @endphp
                    <tr style="background-color: {{ $rowIndex % 2 === 0 ? '#FFFFFF' : '#F6F6FF' }};">
                        <td style="width: 15%;">{{ $item->quantity }}</td>
                        <td>{{ $item->product->name }}</td>
                        <td>
                            {{ $item->product->attributeValues
                            ->filter(fn($attr) => isset($attr->attribute->id) && $attr->attribute->id == 5)
                            ->pluck('value')
                            ->implode(', ') }}
                        </td>
                    </tr>
                @endif
            @endforeach
        </tbody>
    </table>

    <!--     <p class="pedido" style="font-size: 12px; font-weight: bold; color: #8076F8;">Detalle de pedido:</p>
 -->
    <p class="budget">Detalles adicionales de pedido:&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
        <strong>
            {{ $budget->budgetDeliveryData->additional_order_details ?? "" }}
        </strong>
    </p>
    @php
        // Formatea las hasta 3 ventanas de entrega/retiro de la ficha
        // logística como "24 de octubre - 14:30 a 12:30" (la fecha se
        // muestra una sola vez, ya que datetime_from y datetime_to son
        // siempre del mismo día), una por línea. Si la ficha no tiene
        // ventanas cargadas (o no existe), devuelve null para caer al texto
        // único legacy de budget_delivery_data.
        $formatDeliveryWindows = function (?array $windows) {
            if (!$windows) {
                return null;
            }
            $lines = [];
            foreach ($windows as $window) {
                $from = $window['datetime_from'] ?? null;
                $to = $window['datetime_to'] ?? null;
                if (!$from || !$to) {
                    continue;
                }
                try {
                    $fromCarbon = \Illuminate\Support\Carbon::parse($from)->locale('es');
                    $toCarbon = \Illuminate\Support\Carbon::parse($to)->locale('es');
                    $date = $fromCarbon->translatedFormat('j \d\e F');
                    $lines[] = "{$date} - {$fromCarbon->format('H:i')} a {$toCarbon->format('H:i')}";
                } catch (\Throwable $e) {
                    continue;
                }
            }
            return $lines ?: null;
        };
        $deliveryLines = $formatDeliveryWindows($budget->logisticsSheet->delivery_windows ?? null);
        $pickupLines = $formatDeliveryWindows($budget->logisticsSheet->pickup_windows ?? null);
    @endphp
    <table class="budget" style="width: 100%; border-collapse: collapse; background-color: rgb(255, 255, 255);">
        <tr>
            <td style="width: 50%;">
                Coordinación:&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                <strong>
                    {{ $budget->budgetDeliveryData->coordination_contact ?? "" }}&nbsp;-&nbsp;{{ $budget->budgetDeliveryData->cellphone_coordination ?? "" }}
                </strong>
            </td>
            <td style="width: 50%;">
                Recepción:&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                <strong>
                    {{ $budget->budgetDeliveryData->reception_contact ?? "" }}&nbsp;-&nbsp;{{ $budget->budgetDeliveryData->cellphone_reception ?? "" }}
                </strong>
            </td>
        </tr>
        <tr>
            <td style="width: 50%; vertical-align: top;">
                <table style="border-collapse: collapse;">
                    @if($deliveryLines)
                        @foreach($deliveryLines as $line)
                            <tr>
                                <td style="white-space: nowrap; vertical-align: top; padding: 0;">{{ $loop->first ? 'Entrega:' : '' }}&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</td>
                                <td style="padding: 0;"><strong>{{ $line }}</strong></td>
                            </tr>
                        @endforeach
                    @else
                        <tr>
                            <td style="white-space: nowrap; vertical-align: top; padding: 0;">Entrega:&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</td>
                            <td style="padding: 0;"><strong>{{ $budget->budgetDeliveryData->delivery_datetime ?? "" }}</strong></td>
                        </tr>
                    @endif
                </table>
            </td>
            <td style="width: 50%; vertical-align: top;">
                <table style="border-collapse: collapse;">
                    @if($pickupLines)
                        @foreach($pickupLines as $line)
                            <tr>
                                <td style="white-space: nowrap; vertical-align: top; padding: 0;">{{ $loop->first ? 'Retiro:' : '' }}&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</td>
                                <td style="padding: 0;"><strong>{{ $line }}</strong></td>
                            </tr>
                        @endforeach
                    @else
                        <tr>
                            <td style="white-space: nowrap; vertical-align: top; padding: 0;">Retiro:&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</td>
                            <td style="padding: 0;"><strong>{{ $budget->budgetDeliveryData->widthdrawal_datetime ?? "" }}</strong></td>
                        </tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>

    <div class="budget">
        <p style="margin: 8px 0;">Detalles adicionales de entrega:&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
            <strong>
                {{ $budget->budgetDeliveryData->additional_delivery_details ?? "" }}
            </strong>
        </p>
    </div>

    @php
        $insuranceLabels = [
            'yes' => 'Sí',
            'not_applicable' => 'No aplica',
            'later' => 'Se informará luego',
        ];
        $insuranceRequired = $budget->logisticsSheet->insurance_required ?? null;
    @endphp
    <table class="budget" style="width: 100%; border-collapse: collapse; background-color: rgb(255, 255, 255);">
        <tr>
            <td style="width: 25%;">
                Color de almohadones:&nbsp;&nbsp;&nbsp;&nbsp;
                <strong>
                    {{ $budget->logisticsSheet->cushion_color ?? "" }}
                </strong>
            </td>
            <td style="width: 25%;">
                Seguros:&nbsp;&nbsp;&nbsp;&nbsp;
                <strong>
                    {{ $insuranceRequired ? ($insuranceLabels[$insuranceRequired] ?? $insuranceRequired) : "" }}
                    @if($budget->logisticsSheet->insurance_document_path ?? null)
                        &nbsp;-&nbsp;<a href="{{ asset($budget->logisticsSheet->insurance_document_path) }}">Ver documento</a>
                    @endif
                </strong>
            </td>
            <td style="width: 50%;">
                Plano de armado:&nbsp;&nbsp;&nbsp;&nbsp;
                <strong>
                    @if($budget->logisticsSheet->assembly_plan_path ?? null)
                        <a href="{{ asset($budget->logisticsSheet->assembly_plan_path) }}">Ver plano</a>
                    @endif
                </strong>
            </td>
        </tr>
    </table>

    <div class="budget">
        <p style="margin: 8px 0;">Requerimientos adicionales:&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
            <strong>
                {{ $budget->logisticsSheet->additional_requirements ?? "" }}
            </strong>
        </p>
    </div>

    <table class="budget" style="width: 100%; border-collapse: collapse; background-color: rgb(255, 255, 255);">

        <tr>
            <td style="width: 25%;">
                Distancia:&nbsp;&nbsp;&nbsp;&nbsp;
                <strong>
                    {{ $budget->place->distance }}km
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

</body>

</html>