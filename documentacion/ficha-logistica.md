# Ficha Logística

Documentación para el equipo de frontend sobre el formulario público que
completa el cliente con la información logística de su evento (armado,
desarme, contactos, seguros y planos).

Reemplaza el intercambio manual por mail/WhatsApp: el cliente recibe un
**link único** y va completando los datos en distintas visitas, hasta que la
ficha queda completa.

Colección de Postman con ejemplos reales de cada request/response:
[`postman/Logistics Sheet.postman_collection.json`](../postman/Logistics%20Sheet.postman_collection.json).

## Idea general

Hay dos superficies distintas:

1. **Panel admin** (requiere login): un botón/acción en la pantalla del
   presupuesto que llama a un endpoint que arma (o genera, si no existía) el
   link de la ficha y **le manda un mail al cliente** con ese link. Se puede
   volver a llamar más adelante para reenviárselo (por ejemplo si no
   respondió).
2. **Pantalla pública del cliente** (sin login): la página que abre el
   cliente al hacer clic en ese link. Esta es la pantalla que probablemente
   le interese más al frontend, porque hay que construirla.

La ruta que el cliente visita es:

```
LOGISTICS_SHEET_FRONTEND_URL/ficha-logistica/{token}
```

`LOGISTICS_SHEET_FRONTEND_URL` es una variable de entorno del backend (ver
`.env.example`) que apunta a la base del sitio del frontend. El backend arma
el link completo así, pero **la ruta `/ficha-logistica/:token` la tiene que
crear el frontend** — es la pantalla del formulario.

## Autenticación

- Endpoint admin: JWT normal (`Authorization: Bearer {token}`), como el resto
  del panel.
- Endpoints públicos (los que usa la pantalla del cliente): **sin
  autenticación**. La seguridad la da el `token` (uuid) que viene en la URL,
  no un JWT.

## Endpoints

### 1. Admin — obtener/crear la ficha y enviarle el link al cliente por mail

```
GET /api/logistics-sheet/budget/{idBudget}
```

Requiere JWT admin. Si el presupuesto todavía no tiene ficha, la crea con un
token nuevo. **En todos los casos le envía un mail al cliente** (a
`budget.client_mail`) con el link para completar el formulario — llamarlo de
nuevo más adelante reenvía el mismo link, así que sirve tanto para el primer
envío como para un reenvío manual. Devuelve la ficha (con lo que ya esté
cargado) más `public_url` (el link que se mandó) y `mail_sent_to` (a qué
dirección se envió):

```json
{
  "message": "Ficha logística enviada al cliente correctamente",
  "data": {
    "id": 9,
    "id_budget": 12,
    "token": "3f2504e0-4f89-11ee-be56-0242ac120002",
    "...": "resto de los campos de la ficha",
    "public_url": "https://app.galponpueyrredon.com/ficha-logistica/3f2504e0-4f89-11ee-be56-0242ac120002",
    "mail_sent_to": "cliente@correo.com"
  }
}
```

Si el presupuesto no tiene `client_mail` cargado, responde `422` sin enviar
nada (no hay a quién mandarle el mail).

### 2. Público — ver la ficha (precarga del formulario)

```
GET /api/v1/logistics-sheet/{token}
```

Es lo primero que llama la pantalla del cliente al abrir el link. Sin
autenticación. Devuelve:

- `logistics_sheet`: todos los campos ya cargados hasta el momento (para
  precargar el formulario).
- `read_only`: `true` si ya se pasó el plazo de edición (ver más abajo) — en
  ese caso el frontend debería mostrar el formulario deshabilitado.
- `budget`: datos de solo lectura del presupuesto (`client_name`,
  `date_event`, `time_event`, `address` — la dirección del lugar del evento,
  sacada del `place` del presupuesto —, `pdf_url` con el link al PDF del
  presupuesto para que el cliente lo pueda ver/ratificar).
- `event_types`: catálogo `[{id, name, status}]` para el combo de "Tipo de
  evento" (`status` es el estado del tipo de evento — mismo campo que en el
  resto de los catálogos del sistema).

Si el token no existe, responde `404`.

### 3. Público — guardar/actualizar la ficha (carga incremental)

```
POST /api/v1/logistics-sheet/{token}
```

Sin autenticación. **Es incremental**: solo se pisan los campos que se
envían en el body, el resto de la ficha queda igual. Esto permite que el
frontend mande un guardado parcial cada vez que el cliente completa una
pantalla del formulario (no hace falta juntar todo antes de guardar).

Se puede llamar con:
- `Content-Type: application/json` cuando no hay archivos adjuntos en ese
  guardado.
- `multipart/form-data` cuando el request incluye `insurance_document` y/o
  `assembly_plan_document` (obligatorio usar form-data para que viajen los
  archivos; los arrays como `delivery_windows` se mandan con notación de
  corchetes: `delivery_windows[0][datetime_from]`,
  `delivery_windows[0][datetime_to]`, etc., ver ejemplos en la colección de
  Postman).

Respuesta 200 con la ficha actualizada completa. Si la ficha quedó completa
(ver "¿Cuándo se considera completa?"), viene `is_completed: true` y
`completed_at` con la fecha.

Errores:
- `404` — token inválido.
- `422` — error de validación (`errors` con el detalle por campo).
- `403` — la ficha ya no admite modificaciones (se pasó el plazo de edición).

## Campos del formulario

Se agrupan en las mismas 4 secciones/pantallas sugeridas para el form. **M**
= mandatorio, **O** = opcional.

### a) Datos básicos del evento
| Campo | M/O | Tipo |
|---|---|---|
| `budget_ratified` | M | boolean — el cliente confirma que ratifica el presupuesto (mostrar el link `budget.pdf_url` antes de este check) |
| `id_event_type` | M* | int — id de `event_types` (combo) |
| `event_type_other` | M* | string — texto libre si el cliente elige "otra opción" en vez de un tipo del combo |
| ~~`event_start_datetime`~~ | M | **no se manda**: se toma de `budget.date_event` + `budget.time_event` (solo lectura, ver endpoint 2) |
| `event_end_datetime` | M | datetime |
| ~~`address`~~ | M | **no se manda**: se toma de `budget.address` (la dirección del `place` del presupuesto, solo lectura) |
| `address_maps_link` | O | string — link a Google Maps (esto sí lo carga el cliente, es un adicional a la dirección del presupuesto) |
| `accessibility_comments` | O | string |
| `order_contact_name` / `order_contact_phone` | M | string — contacto del pedido |

\* Se manda `id_event_type` **o** `event_type_other`, no hace falta ambos.

`event_start_datetime` y `address` **ya no son campos editables de la
ficha**: se calculan en vivo a partir del presupuesto (`budgets.date_event` +
`budgets.time_event`, y la dirección del `place` asociado al presupuesto).
Así, si un admin edita la fecha/hora o el lugar del evento en el presupuesto,
el cambio se refleja automáticamente en la ficha logística sin que el
cliente tenga que volver a cargar nada. El frontend los debe mostrar como
solo lectura usando los valores que vienen en `budget` (endpoint 2). Por lo
mismo, `event_start_datetime`/`address` **no son válidos** en `field_status`
(no tiene sentido marcarlos "later"/"not_applicable" si no los completa el
cliente) — igual la ficha no se considera completa hasta que el presupuesto
tenga `date_event` y un `place` con dirección cargados.

### b) Armado y desarme
| Campo | M/O | Tipo |
|---|---|---|
| `delivery_windows` | M (solo la 1ra) | array de hasta 3 `{datetime_from, datetime_to}` |
| `pickup_windows` | M (solo la 1ra) | array de hasta 3 `{datetime_from, datetime_to}` |
| `reception_contact_name` / `reception_contact_phone` | M | string |
| `cushion_color` | O | string |
| `additional_order_details` | M | string |
| `delivery_options` | O | string — dirección/nota de retiro, si es distinta a la del `place` del presupuesto |

`datetime_from`/`datetime_to` son fecha+hora completos (no fecha y hora por
separado). `datetime_to` tiene que ser igual o posterior a `datetime_from`.

### c) Requerimientos
| Campo | M/O | Tipo |
|---|---|---|
| `insurance_required` | M | `"yes"` \| `"not_applicable"` \| `"later"` |
| `insurance_document` **o** `insurance_request_text` | M si `insurance_required = "yes"` | archivo (input `insurance_document`, se guarda como `insurance_document_path`) o texto libre (`insurance_request_text`, max 1000) |
| `insurance_additional_documents[]` | O | hasta 5 archivos por request (input array, ver abajo) |
| `additional_requirements` | O | string |

`insurance_document` e `insurance_request_text` son alternativas entre sí:
alcanza con mandar **uno de los dos** para que ese requisito se dé por
resuelto (por ejemplo, si el cliente todavía no tiene el archivo de la
póliza pero puede describir la cobertura o decir que lo va a mandar por
otro medio). Si se manda alguno de los dos, cualquier marca `later`/
`not_applicable` que hubiera en `field_status` para `insurance_document`
se limpia sola.

`insurance_additional_documents` es para sumar documentos de seguro
adicionales al principal (ej. ART, seguro de vehículos) — es un input de
archivos **múltiple**: `insurance_additional_documents[]` con uno o varios
archivos (máximo 5 por request, 10MB c/u). **Es acumulativo**: cada
guardado agrega los nuevos a los que ya había, nunca los reemplaza. No hay
forma de borrar uno ya subido desde este endpoint.

Se guarda en `logistics_sheet.insurance_additional_documents` como un array
de objetos `{path, original_name}`:

```json
{
  "insurance_additional_documents": [
    { "path": "storage/logistics_sheets/12/...art.pdf", "original_name": "ART Constructora.pdf" },
    { "path": "storage/logistics_sheets/12/...rc-auto.pdf", "original_name": "RC Auto.pdf" }
  ]
}
```

### d) Planos
| Campo | M/O | Tipo |
|---|---|---|
| `assembly_plan_document` | O | archivo (input `assembly_plan_document`, se guarda como `assembly_plan_path`) |

Los campos `*_path` que devuelve la API son rutas relativas servidas por el
backend (ej. `storage/logistics_sheets/12/167_poliza.pdf`); para armar la URL
completa hay que prefijarlas con la base del backend.

### Progreso del formulario (opcional)

| Campo | M/O | Tipo |
|---|---|---|
| `completion_percentage` | O | int, 0-100 |

Es un campo puramente informativo: el frontend puede mandar ahí el % de
avance que calcula la propia pantalla (por ejemplo, según cuántas
secciones/pasos del wizard ya se completaron desde el punto de vista de la
UI). El backend lo guarda tal cual y lo devuelve en el `GET`, pero **no lo
usa para nada** — la ficha se considera completa (`is_completed`) según la
regla mandatoria de siempre (ver más abajo), independientemente de lo que
diga `completion_percentage`. Sirve para mostrar una barra de progreso más
granular que el booleano `is_completed`.

## "Se completará más tarde" / "No aplica"

Cada campo (mandatorio u opcional) puede quedar sin resolver todavía. En vez
de mandar el valor, el frontend puede mandar una marca en `field_status`:

```json
{
  "field_status": [
    { "field": "insurance_document", "status": "not_applicable" },
    { "field": "cushion_color", "status": "later" }
  ]
}
```

`status` es `"later"` (se completará más tarde) o `"not_applicable"` (no
aplica). Los nombres válidos de `field` (uno por grupo de campos, no por
columna individual) son:

```
budget_ratified, event_type, event_end_datetime, accessibility_comments,
order_contact, delivery_windows, pickup_windows, reception_contact,
cushion_color, additional_order_details, delivery_options,
insurance_required, insurance_document, additional_requirements,
assembly_plan
```

(`event_start_datetime` y `address` no están en esta lista — ver la nota en
"Datos básicos del evento" más arriba.)

Si en un guardado posterior el cliente manda un valor real para alguno de
esos campos, la marca se limpia sola — no hace falta que el frontend la
saque manualmente.

## ¿Cuándo se considera completa la ficha?

`is_completed: true` cuando **todos** los campos mandatorios tienen valor (o
están marcados `not_applicable`) **y ningún campo** quedó marcado `later`
(ni mandatorio ni opcional). Mientras haya al menos un campo en `later`, la
ficha nunca se considera completa, aunque el resto esté lleno.

El frontend puede usar `is_completed` para mostrar un indicador de progreso o
un mensaje de "ficha completa" al cliente.

## Plazo de edición

Pasado un número de días configurable antes de la fecha del evento
(`date_event` + `time_event` del presupuesto en la tabla `budgets`,
`LOGISTICS_SHEET_EDIT_CUTOFF_DAYS`, default 2), la ficha pasa a ser de solo
lectura:

- El `GET` devuelve `read_only: true`.
- El `POST` devuelve `403`.

El frontend debería chequear `read_only` al cargar la pantalla y, si es
`true`, deshabilitar el formulario (mostrar los datos como texto, sin
inputs) en vez de esperar a que el guardado falle con 403.

## Sincronización con la Ficha de Entrega (`budget_delivery_data`)

Esto sí es relevante para el frontend por los errores que puede devolver
(ver más abajo), aunque el **nombre y la forma de los campos en la API no
cambian** — `id_event_type`, `order_contact_name`/`order_contact_phone`,
`reception_contact_name`/`reception_contact_phone`,
`additional_order_details` y `delivery_options` se siguen mandando y
recibiendo igual que cualquier otro campo de la ficha. Lo que cambia es
dónde se guardan: como esos campos son literalmente los mismos datos que ya
existían en `budget_delivery_data` (la "Ficha de Entrega" que usa el panel
admin para armar/cargar los camiones — modelo `BudgetDeliveryData`,
gestionada aparte por `BudgetDeliveryDataController`), **no se duplican en
`logistics_sheets`**: se leen y escriben directo en `budget_delivery_data`.
Mapeo:

| Campo en la API de la ficha | Columna real en `budget_delivery_data` |
|---|---|
| `id_event_type` | `id_event_type` |
| `order_contact_name` / `order_contact_phone` | `coordination_contact` / `cellphone_coordination` |
| `reception_contact_name` / `reception_contact_phone` | `reception_contact` / `cellphone_reception` |
| `additional_order_details` | `additional_order_details` |
| `delivery_options` | `delivery_options` |

`delivery_options` (string, opcional, max 255) es una dirección/nota de
retiro alternativa a la dirección del `place` del presupuesto — por ejemplo
cuando el retiro no es en la misma dirección del evento. Si se carga, el
PDF de la Ficha de Entrega muestra `delivery_options` en el campo
"Dirección"; si no, muestra la dirección del `place` como hasta ahora.

Además, como mirror best-effort (no bloquean nada si fallan):

| Ficha logística | `budget_delivery_data` |
|---|---|
| `additional_requirements` | `additional_delivery_details` |
| `delivery_windows[0]` (solo la 1ra opción, formateada a texto) | `delivery_datetime` |
| `pickup_windows[0]` (solo la 1ra opción, formateada a texto) | `widthdrawal_datetime` |
| `budget.place.address` | `address` |
| `budget.place.id_locality` | `id_locality` |
| `budget.time_event` | `event_time` |

`delivery_datetime`/`widthdrawal_datetime` son texto libre pensado para
**una sola** ventana (a diferencia de `delivery_windows`/`pickup_windows`,
que soportan hasta 3): por eso solo se espeja ahí la primera opción — la
obligatoria —, nunca las 3 concatenadas (eso desbordaba la columna y
rompía el guardado; quedó corregido).

Solo se pisan los campos para los que hay un valor nuevo en el request (no
se borran datos existentes en `budget_delivery_data` sin equivalente en la
ficha, como `delivery_options`/`widthdrawal_options`, que se siguen
cargando a mano desde el panel). Si este mirror best-effort falla por
cualquier motivo (por ejemplo alguna columna legacy más chica de lo
esperado), no bloquea el guardado de la ficha logística — queda solo un
warning en el log del backend.

**Importante — nuevo caso de error**: `id_event_type` e `id_locality` son
obligatorios (`NOT NULL`) en `budget_delivery_data`. Si todavía no existe el
registro para ese presupuesto y el request manda alguno de los 6 campos de
la tabla de arriba, pero no se puede resolver un `id_event_type` (el cliente
eligió "otra opción" en vez de un tipo del listado) o un `id_locality` (el
presupuesto no tiene `place` asignado), el `POST` devuelve **422** con un
mensaje explicando el motivo, **sin guardar nada de ese request** (ni
siquiera los campos que sí viven en `logistics_sheets`, para no dejar un
estado a medias). El frontend debería mostrar ese mensaje tal cual y, en el
caso de "otra opción", explicarle al cliente que ese dato en particular
(tipo de evento + contactos) queda pendiente hasta que Galpón Pueyrredón
cargue el tipo de evento definitivo. Una vez que exista el registro en
`budget_delivery_data` (aunque sea creado a mano desde el panel admin),
este problema desaparece: las siguientes actualizaciones son un `UPDATE`
normal, sin la restricción de creación.

## Flujo típico

1. Admin abre el presupuesto en el panel → llama a
   `GET /logistics-sheet/budget/{id}` → el backend le manda automáticamente
   un mail al cliente con el link del formulario (`public_url`).
2. Cliente abre el link desde su casilla de mail → frontend llama a
   `GET /v1/logistics-sheet/{token}` → precarga el formulario con lo que ya
   haya.
3. Cliente completa la primera pantalla ("Datos básicos") → frontend hace
   `POST /v1/logistics-sheet/{token}` solo con esos campos.
4. Cliente vuelve otro día y completa "Armado y desarme" + adjunta la póliza
   de seguro → nuevo `POST`, esta vez `multipart/form-data`.
5. Así sucesivamente hasta que `is_completed` da `true`.
6. Si el cliente entra después del plazo de edición, ve todo en modo lectura.

## Fuera de alcance de esta funcionalidad

El envío del mail al llamar al endpoint admin es manual (lo dispara el admin
a demanda, no hay todavía un disparador automático por fecha). No están
implementados (se resuelven en otra tarea): el envío programado según la
cadencia del documento original (al aprobar el presupuesto, 15/10/7/5 días
antes del evento, diario desde los 5 días) ni la integración con WhatsApp o
TEAM UP.
