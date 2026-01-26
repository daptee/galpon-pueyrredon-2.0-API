@component('mail::message')
Hola, gracias por elegir **Galpón Pueyrredón**.

Según sus instrucciones dejamos aprobado el presupuesto de referencia. Le pedimos que chequee detalladamente el contenido del presupuesto para verificar que es el correcto.

@if($replacedBudgetId)
**Nota:** Esta confirmación reemplaza al presupuesto anterior GP{{ str_pad($replacedBudgetId, 5, '0', STR_PAD_LEFT) }}.
@endif

Le recordamos que esta aprobación le concede la responsabilidad del cuidado y el correcto uso de los productos alquilados desde el momento en que son entregados y hasta el momento en que son retirados. Los productos no pueden ser dejados bajo la lluvia ni ser expuestos a entornos hostiles.

Cerca de la fecha del evento nos pondremos en contacto para coordinar todos los aspectos logisticos para poder proveer el servicio.

Quedamos a disposición.

Saludos,
**Mariano** - Galpón Pueyrredón.
Tel - 1152209988

[www.galponpueyrredon.com.ar](http://www.galponpueyrredon.com.ar)
[Instagram - Galpón Pueyrredon](https://www.instagram.com/galponpueyrredon/)
@endcomponent
