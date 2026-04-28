# 02. Requerimientos técnicos

## Stack sugerido

La prueba debe desarrollarse con:

- Laravel moderno.
- PHP 8.2 o superior.
- MySQL o PostgreSQL.
- Jobs/Queues de Laravel.
- Redis, database queue o el driver que el candidato considere adecuado, siempre que explique su decisión.
- Pest o PHPUnit para pruebas básicas.

No es necesario construir frontend.

## Se valorará el uso de

- Form Requests.
- API Resources.
- Enums o constantes para estados.
- Services, Actions o clases de dominio.
- Jobs.
- Migraciones.
- Seeders o factories.
- Logs.
- Trazabilidad.

## Módulos requeridos

El candidato debe implementar:

1. Creación de operación de pago.
2. Recepción de confirmación bancaria en tiempo real.
3. Procesamiento de la confirmación mediante job.
4. Validación de idempotencia.
5. Validación de monto y moneda.
6. Procesamiento de conciliación bancaria de cierre.
7. Simulación de notificación a servicio externo.
8. Consulta de operaciones candidatas a liquidación.

## Estados mínimos esperados

La solución debe manejar como mínimo:

- PENDING
- PAID
- OBSERVED
- RECONCILED

Opcionalmente puede incluir:

- SETTLEMENT_PENDING
- SETTLED
- UNMATCHED
- NOTIFIED

No es obligatorio implementar una máquina de estados compleja, pero sí se evaluará que no existan cambios de estado incoherentes.
