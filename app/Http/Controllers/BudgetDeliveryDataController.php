<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use App\Models\BudgetDeliveryData;
use Illuminate\Http\Request;
use Validator;
use Log;

class BudgetDeliveryDataController extends Controller
{
    public function store(Request $request)
    {
        try {
            $data = $request->all();

            $validator = Validator::make($data, [
                'id_budget' => 'required|exists:budgets,id',
                'id_event_type' => 'required|exists:event_types,id',
                'delivery_options' => 'nullable|string|max:255',
                'widthdrawal_options' => 'nullable|string|max:255',
                'address' => 'nullable|string|max:255',
                'id_locality' => 'required|exists:localities,id',
                'event_time' => 'nullable|date_format:H:i',
                'coordination_contact' => 'nullable|string|max:255',
                'cellphone_coordination' => 'nullable|string|max:20',
                'reception_contact' => 'nullable|string|max:255',
                'cellphone_reception' => 'nullable|string|max:20',
                'additional_delivery_details' => 'nullable|string|max:500',
                'additional_order_details' => 'nullable|string|max:500',
                'delivery_datetime' => 'nullable|date_format:Y-m-d H:i:s',
                'widthdrawal_datetime' => 'nullable|date_format:Y-m-d H:i:s'
            ]);
            if ($validator->fails()) {
                return ApiResponse::create('Error de validación', 422, ['error' => $validator->errors()], [
                    'request' => $request,
                    'module' => 'budget delivery data',
                    'endpoint' => 'Crear datos de entrega',
                ]);
            }

            $budgetDeliveryData = BudgetDeliveryData::create($data);

            $budgetDeliveryData->load( 'budget', 'eventType', 'locality.province');

            return ApiResponse::create('Datos de entrega creados correctamente', 201, $budgetDeliveryData, [
                'request' => $request,
                'module' => 'budget delivery data',
                'endpoint' => 'Crear datos de entrega',
            ]);
        } catch (\Exception $e) {
            return ApiResponse::create('Error al crear los datos de entrega', 500, ['error' => $e->getMessage()], [
                'request' => $request,
                'module' => 'budget delivery data',
                'endpoint' => 'Crear datos de entrega',
            ]);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $budgetDeliveryData = BudgetDeliveryData::find($id);

            if (!$budgetDeliveryData) {
                return ApiResponse::create('Datos de entrega no encontrados', 404, ['error' => 'Datos de entrega no encontrados'], [
                    'request' => $request,
                    'module' => 'budget delivery data',
                    'endpoint' => 'Actualizar datos de entrega',
                ]);
            }

            $validator = Validator::make($request->all(), [
                'id_budget' => 'sometimes|required|exists:budgets,id',
                'id_event_type' => 'sometimes|required|exists:event_types,id',
                'delivery_options' => 'nullable|string|max:255',
                'widthdrawal_options' => 'nullable|string|max:255',
                'address' => 'nullable|string|max:255',
                'id_locality' => 'sometimes|required|exists:localities,id',
                'event_time' => 'nullable|date_format:H:i',
                'coordination_contact' => 'nullable|string|max:255',
                'cellphone_coordination' => 'nullable|string|max:20',
                'reception_contact' => 'nullable|string|max:255',
                'cellphone_reception' => 'nullable|string|max:20',
                'additional_delivery_details' => 'nullable|string|max:500',
                'additional_order_details' => 'nullable|string|max:500',
                'delivery_datetime' => 'nullable|date_format:Y-m-d H:i:s',
                'widthdrawal_datetime' => 'nullable|date_format:Y-m-d H:i:s'
            ]);

            if ($validator->fails()) {
                return ApiResponse::create('Error de validación', 422, ['error' => $validator->errors()], [
                    'request' => $request,
                    'module' => 'budget delivery data',
                    'endpoint' => 'Actualizar datos de entrega',
                ]);
            }

            // no se actualizan los campos que no se envían en la solicitud

            $budgetDeliveryData->fill($request->only([
                'id_budget',
                'id_event_type',
                'delivery_options',
                'widthdrawal_options',
                'address',
                'id_locality',
                'event_time',
                'coordination_contact',
                'cellphone_coordination',
                'reception_contact',
                'cellphone_reception',
                'additional_delivery_details',
                'additional_order_details',
                'delivery_datetime',
                'widthdrawal_datetime'
            ]));

            $budgetDeliveryData->save();

            $budgetDeliveryData->load( 'budget', 'eventType', 'locality.province');

            return ApiResponse::create('Datos de entrega actualizados correctamente', 200, $budgetDeliveryData, [
                'request' => $request,
                'module' => 'budget delivery data',
                'endpoint' => 'Actualizar datos de entrega',
            ]);
        } catch (\Exception $e) {
            return ApiResponse::create('Error al actualizar los datos de entrega', 500, ['error' => $e->getMessage()], [
                'request' => $request,
                'module' => 'budget delivery data',
                'endpoint' => 'Actualizar datos de entrega',
            ]);
        }
    }

}
