@component('mail::message')
# Nuevo presupuesto generado

El cliente **{{ $budget->client_name ?? ($budget->client->name ?? 'Sin nombre') }}** ha generado el presupuesto **Nro {{ $budget->id }}**.

**Datos del presupuesto:**
- **Cliente:** {{ $budget->client_name ?? ($budget->client->name ?? '-') }}
- **Email:** {{ $budget->client_mail ?? '-' }}
- **Teléfono:** {{ $budget->client_phone ?? '-' }}
- **Lugar:** {{ $budget->place->name ?? '-' }}
- **Fecha del evento:** {{ \Carbon\Carbon::parse($budget->date_event)->format('d/m/Y') }}
- **Total:** ${{ number_format($budget->total, 2, ',', '.') }}

Se adjunta el PDF del presupuesto.

Saludos,
**Sistema Galpón Pueyrredón**
@endcomponent
