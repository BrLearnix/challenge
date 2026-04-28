# 01. Contexto de negocio

La empresa procesa pagos para comercios.

Cuando un cliente realiza un pago en el banco, el sistema recibe una confirmación bancaria en tiempo real. Esa confirmación permite registrar operativamente el pago dentro del sistema.

Posteriormente, al cierre del día, el banco envía un archivo o lote de conciliación con el detalle formal de los movimientos procesados. Esta conciliación no debe duplicar pagos, sino validar lo recibido en tiempo real y detectar posibles diferencias.

Además, cuando una operación queda correctamente confirmada como pagada, el sistema debe simular una notificación a un servicio externo, como podría ser un sistema interno de comercios, un proveedor tercero o un módulo de liquidación.

El objetivo es construir una API backend que maneje este flujo de forma segura, evitando duplicados, inconsistencias, reprocesamientos y errores de liquidación.

## Flujo general esperado

1. Se crea una operación de pago.
2. La operación queda en estado PENDING.
3. El banco envía una confirmación en tiempo real.
4. El sistema registra el evento recibido.
5. El procesamiento se realiza mediante un job.
6. Si el monto y la moneda coinciden, la operación pasa a PAID.
7. Si existe una diferencia, la operación pasa a OBSERVED.
8. Al cierre del día, se procesa la conciliación bancaria.
9. La conciliación valida lo recibido en tiempo real.
10. Si todo coincide, la operación queda conciliada.
11. Si hay diferencias, se registra la inconsistencia.
12. Las operaciones válidas pueden aparecer como candidatas a liquidación.
