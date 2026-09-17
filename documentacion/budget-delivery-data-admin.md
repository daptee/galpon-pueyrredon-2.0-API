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

- `delivery_options` / `widthdrawal_options` — texto libre.
- `delivery_datetime` / `widthdrawal_datetime` — texto libre.

## ⚠️ Conflicto con la Ficha Logística

`delivery_datetime` y `widthdrawal_datetime` son los dos campos que la
[ficha logística](ficha-logistica.md) **pisa automáticamente** cada vez que
el cliente guarda una actualización del formulario público. El backend los
arma formateando `delivery_windows`/`pickup_windows` de la ficha (ver
[`syncBudgetDeliveryData()`](../app/Http/Controllers/LogisticsSheetPublicController.php)
en `LogisticsSheetPublicController`) y los vuelca en
`budget_delivery_data.delivery_datetime` / `.widthdrawal_datetime`.

Esto significa:

- Si un admin edita `delivery_datetime` / `widthdrawal_datetime` a mano
  desde este endpoint, y **después** el cliente vuelve a guardar la ficha
  logística (aunque sea para cambiar un campo sin relación, como el color
  de almohadones), esa edición manual **se pisa sola** con lo que diga la
  ficha en ese momento.
- `delivery_options` / `widthdrawal_options`, en cambio, **nunca los toca**
  la ficha logística — quedan 100% a criterio del admin, sin riesgo de que
  se sobrescriban.

### Recomendación

- Para dejar una nota u opción que no se pierda nunca, usar
  `delivery_options` / `widthdrawal_options`.
- Si hace falta fijar `delivery_datetime` / `widthdrawal_datetime` a un
  valor definitivo, hacerlo una vez que la ficha ya pasó su plazo de
  edición (`read_only: true` en el `GET` de la ficha — ver
  [ficha-logistica.md](ficha-logistica.md), sección "Plazo de edición"),
  momento a partir del cual el cliente ya no puede volver a guardar y
  disparar la sincronización.
