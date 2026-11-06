# Buscador global

Un solo endpoint busca en todo el sistema: módulos principales, tablas
auxiliares de Configuración y tutoriales. Devuelve los resultados agrupados por
módulo, con pocos resultados por grupo, pensado para un buscador tipo
"command palette" en el header.

No requiere cambios en la base.

## `GET /api/search`

| Parámetro | Obligatorio | Descripción |
|---|---|---|
| `q` | sí | Texto a buscar. Mínimo 2 caracteres, máximo 100. |
| `limit` | no | Resultados por módulo. Default `5`, máximo `20`. |
| `modules` | no | Limitar a ciertos módulos, separados por coma (ej. `clients,budgets`). Sirve para el "ver más" de un grupo. |

```
GET /api/search?q=juan perez
GET /api/search?q=12791
GET /api/search?q=silla&modules=products&limit=20
```

### Cómo busca

- Separa el texto en palabras y **cada palabra tiene que aparecer** en algún
  campo del módulo. `juan perez` encuentra al cliente con nombre *Juan* y
  apellido *Perez*.
- Si una palabra es un número (con o sin `#`), además busca por **ID exacto**:
  `#12791` trae el presupuesto 12791, el evento 12791 y los pagos de ese
  presupuesto.
- No distingue mayúsculas/minúsculas.

### Respuesta

Solo vienen los módulos que tienen resultados, en este orden: primero los
principales (`group: "main"`), después Configuración (`"config"`) y por último
tutoriales (`"help"`).

```json
{
  "message": "Búsqueda realizada correctamente",
  "data": {
    "query": "ad eventos",
    "total": 7,
    "results": [
      {
        "module": "clients",
        "label": "Clientes",
        "group": "main",
        "has_more": false,
        "items": [
          {
            "id": 62,
            "title": "AD Eventos",
            "subtitle": "info@adeventos.com.ar · Recurrente",
            "status": "activo",
            "meta": null
          }
        ]
      },
      {
        "module": "events",
        "label": "Eventos",
        "group": "main",
        "has_more": true,
        "items": [
          {
            "id": 12919,
            "title": "Evento #12919 - AD Eventos",
            "subtitle": "2028-01-01 · Acassuso",
            "status": "Aprobado",
            "meta": { "date_event": "2028-01-01", "total": "223215.92" }
          }
        ]
      }
    ]
  }
}
```

- `title` / `subtitle` / `status` ya vienen listos para mostrar.
- `has_more: true` → hay más resultados de ese módulo; mostrar "Ver más" y
  pedir `?q=...&modules=<module>&limit=20` (o llevar al listado del módulo con
  el filtro `search`).
- `meta` trae datos extra para navegar cuando el `id` no alcanza (ej. el
  presupuesto de un pago, el módulo de un tutorial).

## `GET /api/search/modules`

Lista de módulos en los que busca, para armar filtros/chips en el front:

```json
{ "data": [ { "module": "users", "label": "Usuarios", "group": "main" }, ... ] }
```

## Módulos y en qué campos busca

### Principales (`main`)

| `module` | Busca en | Navegar a | `meta` |
|---|---|---|---|
| `users` | usuario, nombre, apellido, email, teléfono, ID | detalle del usuario | – |
| `clients` | nombre, apellido, empresa, email, teléfono, CUIT, dirección, **contactos** (nombre, apellido, email, teléfono), ID | detalle del cliente | – |
| `places` | nombre, dirección, teléfono, observaciones, localidad, ID | detalle del lugar | – |
| `transportations` | nombre, ID | detalle del transporte | – |
| `products` | nombre, código, descripción, línea, tipo, ID | detalle del producto | – |
| `budgets` | nombre/email/teléfono del cliente, observaciones, cliente, lugar, ID, ID del presupuesto padre | detalle del presupuesto | `id_budget`, `date_event`, `total` |
| `events` | igual que presupuestos + dirección y contactos de entrega, ID | detalle del evento (`id` = id del presupuesto) | `date_event`, `total` |
| `payments` | observaciones, cliente, tipo y método de pago, ID, ID del presupuesto, monto exacto | listado de pagos del presupuesto | `id_budget`, `amount` |

> Un **evento** es un presupuesto aprobado, así que un presupuesto aprobado
> aparece en los dos grupos. Los presupuestos traen todas las versiones
> (`subtitle` incluye "Versión N"), ordenados del más nuevo al más viejo.

### Configuración (`config`)

Todos buscan por nombre (y por ID si se escribe un número). Navegar a la
pantalla de Configuración correspondiente.

| `module` | Pantalla |
|---|---|
| `user_types` | Tipos de usuario |
| `client_types` | Tipos de cliente |
| `client_classes` | Clases de cliente |
| `place_types` | Tipos de lugar |
| `place_collection_types` | Tipos de cobro de lugar |
| `place_areas` | Áreas de lugar |
| `tolls` | Peajes |
| `client_place_transport_prices` | Precios de traslado por cliente (busca por cliente, lugar y observaciones) |
| `product_lines` | Líneas de producto |
| `product_types` | Tipos de producto |
| `product_furnitures` | Mobiliarios |
| `product_attributes` | Atributos de producto |
| `event_types` | Tipos de evento |
| `payment_types` | Tipos de pago |
| `payment_methods` | Métodos de pago |
| `payment_statuses` | Estados de pago |
| `blocked_dates` | Fechas bloqueadas de logística (busca por motivo y fecha) |

### Tutoriales (`help`)

| `module` | Busca en | `meta` |
|---|---|---|
| `tutorial_modules` | nombre, descripción | – |
| `tutorial_subtopics` | nombre, descripción | `id_tutorial_module` |
| `tutorials` | título, contenido | `id_tutorial_module`, `id_tutorial_subtopic` |

Los usuarios **no internos** solo ven módulos/subtemas activos y tutoriales
publicados (igual que en `/api/tutorials/tree`).

### Lo que no entra (a propósito)

- Estados fijos del sistema (estados de presupuesto, de producto, `status`).
- Provincias y localidades (son datos de referencia; los lugares sí se
  encuentran por localidad).
- Precio hora peón, actualizaciones masivas de precios y configuración de
  capacidad logística: son números/fechas sin texto para buscar.
- Auditoría.

## Permisos

Requiere estar logueado (middleware `admin`), igual que el resto de los
listados. Los permisos por pantalla (`permissions` del usuario) hoy se
controlan en el front, así que **el front tiene que ocultar los grupos de
módulos a los que el usuario no tiene acceso**.

## Recomendaciones para el front

- Disparar la búsqueda con *debounce* (~300 ms) y recién desde 2 caracteres.
- Cancelar el request anterior si el usuario sigue escribiendo.
