# Edición de la Ficha de Entrega (`budget_delivery_data`) desde el admin

Documentación sobre cómo editar manualmente, desde el panel admin, las
opciones de entrega y retiro de un presupuesto — sin pasar por la ficha
logística que completa el cliente.

## Endpoint

```
PUT /api/budget-delivery-data/{id}
```

Requiere JWT admin. Es el mismo [BudgetDeliveryDataController::update](../app/Http/Controllers/BudgetDeliveryDataController.php)
de siempre, sin cambios. Todos los campos son opcionales (`sometimes`): solo
se pisa lo que se manda, el resto queda como estaba.

Campos que acepta:

```
id_budget, id_event_type, delivery_options, widthdrawal_options, address,
id_locality, event_time, coordination_contact, cellphone_coordination,
reception_contact, cellphone_reception, additional_delivery_details,
additional_order_details, delivery_datetime, widthdrawal_datetime
```

Para editar "opciones de entrega y retiro" puntualmente, los campos
relevantes son:

- `delivery_options` — texto libre (dirección/nota de retiro).
- `widthdrawal_options` — texto libre.
- `delivery_datetime` / `widthdrawal_datetime` — texto libre.

## ⚠️ Conflicto con la Ficha Logística

`delivery_datetime`, `widthdrawal_datetime` y `delivery_options` son campos
que la [ficha logística](ficha-logistica.md) **pisa automáticamente** cada
vez que el cliente guarda una actualización del formulario público (ver
[`syncBudgetDeliveryData()`](../app/Http/Controllers/LogisticsSheetPublicController.php)
en `LogisticsSheetPublicController`):

- `delivery_datetime` / `widthdrawal_datetime` se arman formateando
  `delivery_windows`/`pickup_windows` de la ficha.
- `delivery_options` se pisa con lo que el cliente mande en el campo del
  mismo nombre del formulario público (dirección/nota de retiro).

Esto significa:

- Si un admin edita `delivery_datetime`, `widthdrawal_datetime` o
  `delivery_options` a mano desde este endpoint, y **después** el cliente
  vuelve a guardar la ficha logística (aunque sea para cambiar un campo sin
  relación, como el color de almohadones), esa edición manual **se pisa
  sola** — `delivery_datetime`/`widthdrawal_datetime` siempre; `delivery_options`
  solo si el cliente vuelve a mandar valor para ese campo puntual (si no lo
  toca en ese request, lo que el admin cargó queda como está).
- `widthdrawal_options`, en cambio, **nunca lo toca** la ficha logística —
  queda 100% a criterio del admin, sin riesgo de que se sobrescriba.

### Recomendación

- Para dejar una nota que no se pierda nunca pase lo que pase, usar
  `widthdrawal_options` (o, si es para el retiro, coordinarlo con quien
  mantiene la ficha para que no lo pisen desde ahí).
- Si hace falta fijar `delivery_datetime`, `widthdrawal_datetime` o
  `delivery_options` a un valor definitivo, hacerlo una vez que la ficha ya
  pasó su plazo de edición (`read_only: true` en el `GET` de la ficha — ver
  [ficha-logistica.md](ficha-logistica.md), sección "Plazo de edición"),
  momento a partir del cual el cliente ya no puede volver a guardar y
  disparar la sincronización.
