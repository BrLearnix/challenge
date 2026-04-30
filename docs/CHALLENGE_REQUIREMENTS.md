# Backend Technical Challenge

## Prueba técnica para Desarrollador Backend

Este reto técnico forma parte del proceso de selección para la posición de **Desarrollador Backend**.

La prueba busca evaluar criterio real de desarrollo backend, manejo de Laravel/PHP, diseño de APIs, jobs/queues, procesamiento de notificaciones bancarias, conciliación de cierre, integración con servicios externos, base de datos, idempotencia, trazabilidad y lógica transaccional.

No buscamos una solución innecesariamente extensa ni un sistema completo en producción. Buscamos una implementación clara, ordenada y técnicamente bien sustentada, donde se evidencie cómo tomarías decisiones frente a escenarios reales.


# 1. Contexto del caso

La empresa procesa pagos para comercios.

Cuando un cliente realiza un pago en el banco, el sistema recibe una confirmación bancaria en tiempo real. Esa confirmación permite registrar operativamente el pago dentro del sistema.

Posteriormente, al cierre del día, el banco envía un archivo o lote de conciliación con el detalle formal de los movimientos procesados. Esta conciliación no debe duplicar pagos, sino validar lo recibido en tiempo real y detectar posibles diferencias.

Además, cuando una operación queda correctamente confirmada como pagada, el sistema debe simular una notificación a un servicio externo, como podría ser un sistema interno de comercios, un proveedor tercero o un módulo de liquidación.

El objetivo es construir una API backend que maneje este flujo de forma segura, evitando duplicados, inconsistencias, reprocesamientos y errores de liquidación.


# 2. Stack sugerido

La prueba debe desarrollarse con:

- Laravel moderno.
- PHP 8.2 o superior.
- MySQL o PostgreSQL.
- Jobs/Queues de Laravel.
- Redis, database queue o el driver que consideres adecuado, siempre que expliques tu decisión.
- Pest o PHPUnit para pruebas básicas.

No es necesario construir frontend.

Se valorará el uso adecuado de:

- Form Requests.
- API Resources.
- Enums o constantes para estados.
- Services, Actions o clases de dominio.
- Jobs.
- Migraciones.
- Seeders o factories, si lo consideras necesario.
- Logs y trazabilidad.


# 3. Tiempo esperado

La prueba está pensada para resolverse en aproximadamente **4-6 horas**.

Si por tiempo decides no implementar algún detalle secundario, puedes dejarlo explicado en el README de tu solución indicando cómo lo abordarías en un entorno de producción y por qué.


# 4. Alcance de la prueba

Debes implementar los siguientes módulos:

1. Crear operación de pago.
2. Recibir confirmación bancaria en tiempo real.
3. Procesar la confirmación mediante un job.
4. Evitar duplicados por `event_id` y `bank_transaction_id`.
5. Validar monto y moneda.
6. Marcar operaciones como `PAID` u `OBSERVED` según corresponda.
7. Procesar conciliación bancaria de cierre del día.
8. Detectar coincidencias, diferencias y movimientos no encontrados.
9. Simular una notificación a un servicio externo cuando una operación queda `PAID`.
10. Consultar operaciones candidatas a liquidación considerando estado, conciliación y hora de corte.


# 5. Módulo A: Crear operación de pago

## Endpoint

```http
POST /api/v1/payments
```

## Payload de ejemplo

```json
{
  "merchant_id": 10,
  "customer_document": "76359665",
  "amount": 150.50,
  "currency": "PEN",
  "description": "Pago de servicio mensual"
}
```

## Reglas esperadas

La operación debe crearse con:

- Código único de pago.
- Estado inicial `PENDING`.
- Monto.
- Moneda.
- Comercio asociado.
- Documento del cliente.
- Fecha de creación.

Ejemplo de código de pago:

```text
LTP-20260424-000001
```

Ejemplo de respuesta:

```json
{
  "payment_code": "LTP-20260424-000001",
  "status": "PENDING",
  "amount": "150.50",
  "currency": "PEN"
}
```

Se valorará que el manejo de montos evite errores de precisión, por ejemplo usando centavos como entero o `DECIMAL` con una justificación clara.


# 6. Módulo B: Recibir confirmación bancaria en tiempo real

## Endpoint

```http
POST /api/v1/bank/notifications
```

## Payload de ejemplo

```json
{
  "event_id": "evt_001",
  "bank_transaction_id": "bank_tx_999",
  "payment_code": "LTP-20260424-000001",
  "amount": 150.50,
  "currency": "PEN",
  "status": "PAID",
  "paid_at": "2026-04-24 20:44:00"
}
```

## Reglas esperadas

- Validar autenticidad mediante token, firma simple, HMAC o mecanismo equivalente.
- Guardar el payload original recibido.
- No procesar toda la lógica directamente en el controller.
- Registrar el evento recibido.
- Despachar un job para procesar la confirmación.
- Evitar procesar dos veces el mismo `event_id`.
- Evitar duplicar el pago si llega dos veces el mismo `bank_transaction_id`.
- Si el monto no coincide con la operación original, la operación debe quedar `OBSERVED`.
- Si la moneda no coincide, la operación debe quedar `OBSERVED`.
- Si todo coincide, la operación debe quedar `PAID`.
- Debe quedar trazabilidad de la decisión tomada.

El job puede llamarse, por ejemplo:

```text
ProcessBankNotificationJob
```


# 7. Módulo C: Procesar la confirmación mediante job

La confirmación bancaria debe procesarse mediante un job, no directamente dentro del controller.

El job debe encargarse de:

- Buscar la operación por `payment_code`.
- Validar que el evento no haya sido procesado previamente.
- Validar monto.
- Validar moneda.
- Validar estado actual de la operación.
- Actualizar la operación a `PAID` u `OBSERVED`, según corresponda.
- Registrar auditoría o trazabilidad.
- Manejar errores de forma controlada.

Se valorará el uso de transacciones de base de datos cuando corresponda.


# 8. Módulo D: Procesar conciliación de cierre del día

## Endpoint

```http
POST /api/v1/bank/reconciliation
```

## Payload de ejemplo

```json
{
  "bank": "BANK_A",
  "process_date": "2026-04-24",
  "movements": [
    {
      "bank_movement_id": "mov_001",
      "bank_transaction_id": "bank_tx_999",
      "payment_code": "LTP-20260424-000001",
      "amount": 150.50,
      "currency": "PEN",
      "paid_at": "2026-04-24 20:44:30"
    },
    {
      "bank_movement_id": "mov_002",
      "bank_transaction_id": "bank_tx_1000",
      "payment_code": "LTP-20260424-000002",
      "amount": 200.00,
      "currency": "PEN",
      "paid_at": "2026-04-24 20:46:00"
    }
  ]
}
```

## Reglas esperadas

- No procesar dos veces el mismo `bank_movement_id`.
- Si el movimiento coincide con una operación ya confirmada en tiempo real, debe quedar conciliado sin duplicar el pago.
- Si el movimiento trae monto o moneda diferente, debe registrarse como inconsistencia.
- Si el `payment_code` no existe, el movimiento debe quedar como no conciliado o `UNMATCHED`.
- Si el movimiento existe en conciliación pero nunca llegó por tiempo real, puedes procesarlo como confirmación tardía o dejarlo observado. Debes explicar tu decisión.
- Debe quedar trazabilidad de lo procesado, duplicado, observado o no conciliado.


# 9. Módulo E: Simular notificación a servicio externo

Cuando una operación quede correctamente confirmada como `PAID`, el sistema debe simular una notificación a un servicio externo.

Puedes implementar una clase o servicio, por ejemplo:

```text
PaymentNotificationClient
```

Y un job, por ejemplo:

```text
NotifyPaymentConfirmedJob
```

No es necesario conectarse a un proveedor real. Puedes simular la integración mediante una clase fake, mock, endpoint interno o servicio local.

## Reglas esperadas

- La notificación externa no debe ejecutarse directamente desde el controller.
- Debe ejecutarse mediante un job.
- Debe registrarse el intento de notificación.
- Debe evitarse notificar dos veces la misma operación.
- Debe manejar al menos un caso de error simulado.
- Debes explicar cómo manejarías timeouts, errores 500 o respuestas inválidas en producción.

Se valorará que el diseño permita reemplazar fácilmente el servicio simulado por una integración real.


# 10. Módulo F: Consultar pagos candidatos a liquidación

## Endpoint

```http
GET /api/v1/settlements/candidates
```

Debe devolver operaciones aptas para liquidación.

## Reglas esperadas

- Solo deben aparecer operaciones `PAID`.
- No deben aparecer operaciones `OBSERVED`.
- No deben aparecer operaciones con diferencias de conciliación.
- No deben aparecer operaciones ya liquidadas, si decides implementar una marca básica de liquidación.
- Debe considerarse la hora de corte `20:45`.
- La zona horaria debe ser `America/Lima`.
- Si el pago fue confirmado hasta las `20:45`, puede pasar a liquidación del siguiente día hábil.
- Si fue confirmado después de las `20:45`, puede pasar a liquidación del subsiguiente día hábil.

No es obligatorio implementar toda la liquidación contable. Basta con identificar correctamente qué pagos serían candidatos.


# 11. Estados mínimos

Debes manejar como mínimo estos estados:

- `PENDING`
- `PAID`
- `OBSERVED`
- `RECONCILED`

Opcionalmente puedes incluir:

- `SETTLEMENT_PENDING`
- `SETTLED`
- `UNMATCHED`
- `NOTIFIED`

No es obligatorio implementar una máquina de estados compleja, pero sí se evaluará que no existan cambios de estado incoherentes.

Por ejemplo, una operación correctamente confirmada como `PAID` no debería volver a `PENDING` ni ser sobrescrita sin una regla válida.


# 12. Base de datos

Debes crear las tablas que consideres necesarias.

Como mínimo, esperamos una estructura equivalente a:

- `payments`
- `bank_notifications` o `bank_events`
- `bank_reconciliation_movements`
- `payment_audits` o tabla equivalente de trazabilidad
- `external_notifications` o tabla equivalente para registrar notificaciones externas

Se valorará que uses restricciones o índices para proteger la integridad del sistema, por ejemplo:

- `payment_code` único.
- `event_id` único.
- `bank_transaction_id` indexado o único según tu criterio.
- `bank + bank_movement_id` único.
- `payment_id` único o controlado en la tabla de notificaciones externas.
- Índices para consultar pagos por estado, comercio y fecha.

Incluye en el README de tu solución una breve explicación de los índices principales y por qué los creaste.


# 13. Pruebas mínimas esperadas

Incluye tests con Pest o PHPUnit para validar, como mínimo:

1. Crear una operación `PENDING`.
2. Confirmar una operación con notificación bancaria válida.
3. Evitar duplicar el procesamiento si llega dos veces el mismo `event_id`.
4. Evitar duplicar el pago si llega dos veces el mismo `bank_transaction_id`.
5. Marcar como `OBSERVED` una operación con monto incorrecto.
6. Procesar conciliación de cierre para una operación ya confirmada en tiempo real.
7. Detectar una diferencia entre la confirmación en tiempo real y la conciliación.
8. Registrar o simular la notificación externa cuando una operación queda `PAID`.

No buscamos cobertura total, pero sí pruebas que demuestren que validaste los casos críticos.


# 14. Entregables

Debes entregar:

1. Repositorio Git o archivo comprimido con el código.
2. README con instrucciones de instalación y ejecución.
3. Archivo `.env.example`.
4. Migraciones.
5. Tests automatizados.
6. Documentación breve de endpoints o colección Postman/Insomnia.
7. Explicación breve de cómo ejecutar el queue worker.
8. Explicación breve de tus decisiones de base de datos, índices e idempotencia.
9. Explicación breve de cómo estructuraste la integración externa simulada.


# 15. Criterios de evaluación

La prueba será evaluada sobre 100 puntos.

| Criterio | Puntaje |
|---|---:|
| Claridad y orden del proyecto | 10 |
| Correcto uso de Laravel y PHP | 10 |
| Diseño de API y validaciones | 10 |
| Uso real de jobs/queues | 12 |
| Manejo de idempotencia | 14 |
| Seguridad básica de la notificación bancaria | 6 |
| Modelado de base de datos | 10 |
| Índices y restricciones | 10 |
| Manejo correcto de estados | 6 |
| Trazabilidad de eventos | 6 |
| Integración externa simulada | 6 |
| Tests automatizados | 6 |
| Documentación | 4 |
| Total | 100 |

## Interpretación

| Puntaje | Resultado |
|---|---|
| 85 a 100 | Muy buen candidato |
| 75 a 84 | Apto para entrevista técnica final |
| 65 a 74 | Evaluar solo si el perfil y expectativa salarial son convenientes |
| Menos de 65 | No recomendable para continuar |


# 16. Presentación técnica posterior

Luego de revisar la solución, podremos coordinar una presentación técnica.

La presentación debe durar aproximadamente entre 15 y 20 minutos, seguida de preguntas técnicas.

El objetivo será entender no solo qué implementaste, sino por qué lo implementaste de esa manera.

## Puntos que debes preparar

1. Resumen general de la solución implementada.
2. Arquitectura del proyecto y organización del código.
3. Flujo completo de una operación desde su creación hasta quedar candidata a liquidación.
4. Confirmación bancaria en tiempo real.
5. Procesamiento mediante jobs/queues.
6. Manejo de idempotencia y eventos duplicados.
7. Modelo de base de datos, índices y restricciones.
8. Procesamiento de conciliación de cierre.
9. Manejo de operaciones observadas o inconsistentes.
10. Simulación de notificación a un servicio externo.
11. Tests implementados.
12. Mejoras que aplicarías en producción.


# 17. Entrega

La entrega debe realizarse mediante un repositorio privado de GitHub.

Debes agregar como colaborador al usuario de GitHub latinpay123 o compartir el acceso correspondiente.


# 18. Plazo

El plazo de entrega será hasta:

```text
Lunes 04 de Mayo del 2026 a las 11:30 am
```

Luego de revisar tu solución y asignarte un puntaje, coordinaremos una entrevista técnica final para conversar sobre tus decisiones, estructura, manejo de casos borde y posibles mejoras.


# 19. Nota final

El objetivo de esta prueba no es medir únicamente velocidad de desarrollo, sino criterio técnico, orden, comprensión del flujo transaccional y capacidad para diseñar una solución mantenible.
