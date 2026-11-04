# Cambios en la API que afectan al front

## 1. El hash de la contraseña ya no viaja en las respuestas

El campo `password` (y `remember_token`) del usuario ya no se devuelve en ningún endpoint, tampoco en el `user` que viene anidado en los pagos.

Endpoints donde antes venía:

- `POST user` (Usuarios → Nuevo usuario)
- `PUT user/own` (Mi perfil → Guardar datos)
- `GET payment` (Pagos y Eventos → Pagos del evento)
- `POST payment` (Registrar pago)
- `PUT payment/update-status/{id}` (Aprobar / anular un pago)

**Front:** si algo leía `user.password` de una respuesta, deja de existir.

## 2. Desactivar un área de lugares

`PUT places-areas/{id}` ahora acepta `status` con los mismos valores que el resto de los catálogos:

| status | Significado |
|---|---|
| `1` | Activo |
| `2` | Inactivo |

Antes solo aceptaba `0` o `1`, por eso no se podía desactivar. **Front:** para desactivar, mandar `status: 2`.

## 3. Link de Google Maps de un lugar

Nuevo campo `maps_link` (string, opcional, máx. 500 caracteres) en lugares.

- `POST places` y `PUT places/{id}`: se manda `maps_link`. Mandar `null` lo vacía.
- `GET places` y `GET places/{id}`: viene `maps_link` en cada lugar.

```json
{
  "name": "Salón Norte",
  "address": "Av. Siempre Viva 123",
  "maps_link": "https://maps.app.goo.gl/xxxxx"
}
```

**Front:** el campo se tiene que llamar exactamente `maps_link`. Antes se descartaba cualquier dato que se mandara.

## 4. Filtro por estado en tablas de configuración

Estos listados ahora respetan el parámetro `status` (`1` = activo, `2` = inactivo). Sin `status` devuelven todos los registros, como antes.

- `GET product-line`
- `GET product-furniture`
- `GET product-attribute`
- `GET pawn-hour-price`
- `GET event-type`

Ejemplo: `GET product-line?status=2`

### Paginación en `event-type`

`GET event-type` ahora pagina si se manda `per_page` (y opcionalmente `page`), igual que los otros listados. La respuesta trae `meta_data`:

```json
{
  "message": "Tipos de eventos traídos correctamente",
  "data": [ ... ],
  "meta_data": { "page": 1, "per_page": 10, "total": 8, "last_page": 1 }
}
```

Sin `per_page` devuelve todos los registros y `meta_data: null` (no cambia nada para las pantallas que ya lo usan así).

## 5. Editar un tarifario ya no borra y recrea los tramos

`PUT client-place-transport-prices/{id}`: cada tramo en `items` puede llevar su `id`.

| En el request | Qué pasa |
|---|---|
| Tramo con `id` | Se actualiza ese tramo |
| Tramo sin `id` | Se crea un tramo nuevo |
| Tramo existente que no viene en `items` | Se elimina |

```json
{
  "items": [
    { "id": 20, "max_volume": 20, "price": 500 },
    { "id": 21, "max_volume": 30, "price": 800 },
    { "max_volume": 50, "price": 1200 }
  ]
}
```

**Front:** mandar el `id` de los tramos existentes (viene en el `GET`). Si no se manda, el tramo se borra y se crea uno nuevo con otro id. Eso rompe la referencia de los presupuestos que ya usaban ese tramo.

## 6. Códigos de error

Antes muchos errores que no eran del servidor devolvían `500`. Ahora el código depende del tipo de error, en todos los endpoints:

| Código | Cuándo |
|---|---|
| `422` | Error de validación (datos faltantes o inválidos) |
| `404` | El registro no existe |
| `403` | Acceso denegado |
| `409` | Registro duplicado o con datos relacionados que impiden la operación |
| `500` | Error real del servidor |

El formato de la respuesta no cambia:

```json
{
  "message": "Error de validación",
  "data": null,
  "error": [ { "campo": ["mensaje"] } ]
}
```

Casos puntuales:

- **Cambiar contraseña** con la contraseña actual incorrecta: antes `500`, ahora `422`.
  ```json
  {
    "message": "La contraseña actual es incorrecta",
    "data": null,
    "error": { "current_password": ["La contraseña actual es incorrecta"] }
  }
  ```
- **`GET user/{id}`** con un usuario inexistente: antes `500`, ahora `404`.

**Front:** revisar el manejo de errores que asumía `500` para estos casos. Conviene mostrar `message` (y los mensajes por campo en los `422`) en vez de un error genérico.
