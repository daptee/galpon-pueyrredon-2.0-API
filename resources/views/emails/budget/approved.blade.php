@component('mail::message')
Hola, gracias por elegir **Galpón Pueyrredón**

Hemos confirmado el presupuesto de referencia. Le pedimos que chequee detalladamente que el contenido del presupuesto se ajusta a lo solicitado.

@if($replacedBudgetId)
**Nota:** Esta confirmación reemplaza al presupuesto anterior GP{{ str_pad($replacedBudgetId, 5, '0', STR_PAD_LEFT) }}.
@endif

@if($logisticsSheetUrl)
Te dejamos aquí la ficha logística para que completes los datos que necesitamos para poder organizar la entrega y también todo aquello que creas que puede ser útil para satisfacer tu pedido. Si no tienes los datos en este momento, puedes completar solo aquellos que tengas ahora mismo, y completar la carga hasta 5 días previos al evento.

@component('mail::button', ['url' => $logisticsSheetUrl])
Completar ficha logística
@endcomponent
@endif

Le recordamos que esta aprobación le concede la responsabilidad del cuidado y el correcto uso de los productos alquilados desde el momento en que son entregados y hasta el momento en que son retirados. Los productos no pueden ser dejados bajo la lluvia ni ser expuestos a entornos hostiles.

Quedamos a disposición.

Saludos, **Mariano** - Galpón Pueyrredón. Tel - 1152209988

[www.galponpueyrredon.com.ar](http://www.galponpueyrredon.com.ar)
[Instagram - Galpón Pueyrredon](https://www.instagram.com/galponpueyrredon/)
@endcomponent
