@component('mail::message')
Hola, gracias por contactarte con **Galpón Pueyrredón**.

Estoy adjuntando presupuesto según lo solicitado.

El costo del traslado incluye el servicio de entrega y retiro con personal asegurado para el volumen presupuestado.  
Si no fue advertido en su pedido y se cotiza para un sitio no identificado en el presupuesto (nombre de salón, hotel, etc.), el costo del traslado supone la disposición de un rango mínimo de 4hs para el armado y de 3hs para el retiro, entregándose en planta baja o mismo nivel de acceso vehicular y con desplazamientos de no más de 35mts.

El cliente se hace responsable de los productos alquilados desde el momento en que son entregados y hasta el momento en que son retirados.

Quedamos a disposición.

Saludos,  
**{{ $user->name }}** - Galpón Pueyrredón.  
Tel - {{ $user->phone }}

[www.galponpueyrredon.com.ar](http://www.galponpueyrredon.com.ar)  
[Instagram - Galpón Pueyrredon](https://www.instagram.com/galponpueyrredon/)
@endcomponent
