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
   presupuesto que llama a un endpoint para obtener (o generar) el link de la
   ficha y poder copiarlo/enviarlo al cliente.
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

### 1. Admin — obtener o crear la ficha de un presupuesto

```
GET /api/logistics-sheet/budget/{idBudget}
```

Requiere JWT admin. Si el presupuesto todavía no tiene ficha, la crea con un
token nuevo. Devuelve la ficha (con lo que ya esté cargado) más un
`public_url` ya armado, listo para copiar y enviar:

```json
{
  "message": "Ficha logística obtenida correctamente",
  "data": {
    "id": 9,
    "id_budget": 12,
    "token": "3f2504e0-4f89-11ee-be56-0242ac120002",
    "...": "resto de los campos de la ficha",
    "public_url": "https://app.galponpueyrredon.com/ficha-logistica/3f2504e0-4f89-11ee-be56-0242ac120002"
  }
}
```

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
  `date_event`, `time_event`, `pdf_url` con el link al PDF del presupuesto
  para que el cliente lo pueda ver/ratificar).
- `event_types`: catálogo `[{id, name}]` para el combo de "Tipo de evento".

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
  corchetes: `delivery_windows[0][date]`, `delivery_windows[0][time_from]`,
  etc., ver ejemplos en la colección de Postman).

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
| `event_start_datetime` | M | datetime |
| `event_end_datetime` | M | datetime |
| `address` | M | string |
| `address_maps_link` | O | string — link a Google Maps |
| `accessibility_comments` | O | string |
| `order_contact_name` / `order_contact_phone` | M | string — contacto del pedido |

\* Se manda `id_event_type` **o** `event_type_other`, no hace falta ambos.

### b) Armado y desarme
| Campo | M/O | Tipo |
|---|---|---|
| `delivery_windows` | M (solo la 1ra) | array de hasta 3 `{date, time_from, time_to}` |
| `pickup_windows` | M (solo la 1ra) | array de hasta 3 `{date, time_from, time_to}` |
| `reception_contact_name` / `reception_contact_phone` | M | string |
| `cushion_color` | O | string |
| `additional_order_details` | M | string |

### c) Requerimientos
| Campo | M/O | Tipo |
|---|---|---|
| `insurance_required` | M | `"yes"` \| `"not_applicable"` \| `"later"` |
| `insurance_document` | M si `insurance_required = "yes"` | archivo (input `insurance_document`, se guarda como `insurance_document_path`) |
| `additional_requirements` | O | string |

### d) Planos
| Campo | M/O | Tipo |
|---|---|---|
| `assembly_plan_document` | O | archivo (input `assembly_plan_document`, se guarda como `assembly_plan_path`) |

Los campos `*_path` que devuelve la API son rutas relativas servidas por el
backend (ej. `storage/logistics_sheets/12/167_poliza.pdf`); para armar la URL
completa hay que prefijarlas con la base del backend.

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
budget_ratified, event_type, event_start_datetime, event_end_datetime,
address, accessibility_comments, order_contact, delivery_windows,
pickup_windows, reception_contact, cushion_color, additional_order_details,
insurance_required, insurance_document, additional_requirements,
assembly_plan
```

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

Pasado un número de días configurable antes de `event_start_datetime`
(`LOGISTICS_SHEET_EDIT_CUTOFF_DAYS`, default 2), la ficha pasa a ser de solo
lectura:

- El `GET` devuelve `read_only: true`.
- El `POST` devuelve `403`.

El frontend debería chequear `read_only` al cargar la pantalla y, si es
`true`, deshabilitar el formulario (mostrar los datos como texto, sin
inputs) en vez de esperar a que el guardado falle con 403.

## Flujo típico

1. Admin abre el presupuesto en el panel → llama a
   `GET /logistics-sheet/budget/{id}` → copia `public_url` y se lo manda al
   cliente por mail/WhatsApp (esto lo hace a mano, no está automatizado).
2. Cliente abre el link → frontend llama a
   `GET /v1/logistics-sheet/{token}` → precarga el formulario con lo que ya
   haya.
3. Cliente completa la primera pantalla ("Datos básicos") → frontend hace
   `POST /v1/logistics-sheet/{token}` solo con esos campos.
4. Cliente vuelve otro día y completa "Armado y desarme" + adjunta la póliza
   de seguro → nuevo `POST`, esta vez `multipart/form-data`.
5. Así sucesivamente hasta que `is_completed` da `true`.
6. Si el cliente entra después del plazo de edición, ve todo en modo lectura.

## Fuera de alcance de esta funcionalidad

No están implementados (se resuelven en otra tarea): el envío automático
programado de los mails/recordatorios (al aprobar el presupuesto, 15/10/7/5
días antes del evento) ni la integración con WhatsApp o TEAM UP. Hoy el envío
del link es manual, copiando el `public_url`.
