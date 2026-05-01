## Prueba técnica Backend (Latinpay)

Solución en **Laravel 12** / **PHP 8.2+**. Enunciado: [`docs/CHALLENGE_REQUIREMENTS.md`](docs/CHALLENGE_REQUIREMENTS.md).

---

### Documentación para revisores y QA

| Recurso | Descripción |
|---------|-------------|
| **[Guía de API y pruebas manuales](docs/API.md)** | Arranque, flujo happy path con **curl**, autenticación webhook (Bearer / HMAC), colas, tests y tabla de incidencias frecuentes. |
| **Postman** | Colección: [`docs/postman/Latinpay-Challenge.postman_collection.json`](docs/postman/Latinpay-Challenge.postman_collection.json). Entorno ejemplo: [`docs/postman/Latinpay-Challenge.postman_environment.json`](docs/postman/Latinpay-Challenge.postman_environment.json). Importar ambos y definir `webhook_secret` igual que `BANK_WEBHOOK_SECRET` en `.env`. |
| **Insomnia** | Importar la colección Postman desde archivo o replicar peticiones según `docs/API.md`. |

---

### Requisitos e instalación

- PHP 8.2+, Composer, extensiones habituales de Laravel.
- SQLite (por defecto en `.env.example`) o MySQL configurando `DB_*`.

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed
php artisan serve
```

Configure **`BANK_WEBHOOK_SECRET`** en `.env` antes de llamar a los webhooks. Opcional: **`PAYMENT_NOTIFICATION_SIMULATE_ERROR=true`** para forzar un fallo simulado en el primer intento de notificación externa.

Para una demo en **2 minutos** sin worker, deje **`QUEUE_CONNECTION=sync`** en `.env` (los jobs corren dentro del mismo request). Si usa **`database`**, abra otra terminal con `php artisan queue:work`.

---

### 🚀 Quick test (≈2 minutos)

Flujo mínimo que puede copiar y pegar (Linux/macOS/Git Bash). Ajuste `BASE`, `SECRET` y las fechas si lo necesita.

```bash
export BASE=http://127.0.0.1:8000
export SECRET='pegue_aqui_BANK_WEBHOOK_SECRET'

# 1. Crear pago → copie payment_code de la respuesta en PAYMENT_CODE
curl -s -X POST "$BASE/api/v1/payments" \
  -H "Content-Type: application/json" -H "Accept: application/json" \
  -d '{"merchant_id":1,"customer_document":"76359665","amount":150.50,"currency":"PEN","description":"Quick test"}'

# 2. Webhook banco (event_id y bank_transaction_id únicos por intento)
curl -s -X POST "$BASE/api/v1/bank/notifications" \
  -H "Content-Type: application/json" -H "Accept: application/json" \
  -H "Authorization: Bearer $SECRET" \
  -d '{"event_id":"evt_quick_001","bank_transaction_id":"bank_tx_quick_001","payment_code":"PEGUE_PAYMENT_CODE","amount":150.50,"currency":"PEN","status":"PAID","paid_at":"2026-04-24 20:44:00"}'

# 3. Conciliación de cierre (mismo payment_code y mismo bank_transaction_id que el paso 2)
curl -s -X POST "$BASE/api/v1/bank/reconciliation" \
  -H "Content-Type: application/json" -H "Accept: application/json" \
  -H "Authorization: Bearer $SECRET" \
  -d '{"bank":"BANK_A","process_date":"2026-04-24","movements":[{"bank_movement_id":"mov_quick_001","bank_transaction_id":"bank_tx_quick_001","payment_code":"PEGUE_PAYMENT_CODE","amount":150.50,"currency":"PEN","paid_at":"2026-04-24 20:44:30"}]}'

# 4. Candidatos a liquidación (ej.: pago antes del corte 20:45 el viernes → suele ser elegible desde el lunes siguiente)
curl -s "$BASE/api/v1/settlements/candidates?as_of=2026-04-27" -H "Accept: application/json"
```

Respuestas esperadas: **201** (pago), **202** (webhook aceptado), **200** (conciliación), **200** con `candidates` (liquidación). Detalle y variantes: [`docs/API.md`](docs/API.md).

---

### ❗ Notas importantes

- Si un pago tiene `reconciliation_match = DISCREPANCY`, **no aparece** en candidatos a liquidación hasta que ese flag se corrija en un diseño real (aquí queda excluido a propósito).
- **`as_of`** en settlements es una fecha de valoración en **America/Lima**: la primera fecha hábil en que el pago es elegible depende de **`paid_at`** y del corte **20:45**. Si consulta el **mismo día calendario** que el pago y ese día es anterior al “primer día hábil elegible”, la lista puede venir **vacía** sin que el sistema esté roto.
- Operaciones **`OBSERVED`** o **`PENDING`** no son candidatas; **`settled_at`** no nulo tampoco.
- En conciliación, un segundo movimiento con el mismo **`bank_movement_id`** dentro del **mismo** lote (`bank` + `process_date`) se **omite** como duplicado (ver `summary.duplicates_skipped`).
- Candidatos incluyen estados **`PAID`** y **`RECONCILED`** sin discrepancia, alineado al flujo “confirmado y cuadrado con el banco”.

---

### 🔄 Flujo del sistema

```
Payment (PENDING)
    → POST /bank/notifications (webhook autenticado)
    → ProcessBankNotificationJob → PAID u OBSERVED (+ auditoría)
    → POST /bank/reconciliation → RECONCILED + MATCHED, DISCREPANCY o movimiento UNMATCHED (+ auditoría)
    → NotifyPaymentConfirmedJob (tras PAID / confirmación tardía) → registro en external_notifications
    → GET /settlements/candidates (filtros: estado, discrepancia, settled_at, reglas 20:45 Lima)
```

---

### 🧠 Decisiones de diseño

- **Montos:** se guardan en **centavos (`amount_minor`)** para evitar errores de coma flotante; en API se exponen como decimal con formato controlado.
- **Idempotencia en capas:** unicidad de **`event_id`** y **`bank_transaction_id`** en notificaciones; **`bank_movement_id`** único por lote de conciliación; **`payment_id`** único en notificaciones externas; jobs toleran reintentos sin doble efecto donde aplica.
- **Conciliación vs tiempo real:** para un pago ya **PAID** por webhook, el match exige coherencia de importes y una notificación **PAID** previa con el mismo **`bank_transaction_id`**; si el pago sigue **PENDING**, el cierre puede actuar como **confirmación tardía** (documentado en código y en `docs/API.md`).
- **Desacoplamiento:** la lógica pesada del banco y la notificación al comercio **no** va en el controller; va en **Actions** + **Jobs** (`ShouldQueue`), con transacciones DB en los puntos críticos.
- **Integración externa:** contrato **`PaymentNotificationClient`** + implementación **`FakePaymentNotificationClient`** intercambiable en `AppServiceProvider`.

---

### ⚠️ Problemas comunes

- **`QUEUE_CONNECTION=database` sin worker:** los webhooks quedan en **202** pero el pago no cambia hasta ejecutar **`php artisan queue:work`**.
- **`BANK_WEBHOOK_SECRET` vacío:** respuesta **503** (“no configurado”); con secreto incorrecto → **401**.
- **`Authorization` mal puesto en webhooks:** debe ser literalmente **`Bearer <mismo valor que .env>`** (con espacio después de `Bearer`).
- **Settlement vacío:** revise **`as_of`** (fecha hábil posterior al pago según corte 20:45), **`merchant_id`** si filtra, y que no sea **`DISCREPANCY` / OBSERVED / settled**.
- **Segunda línea de conciliación “no hace nada”:** mismo **`bank_movement_id`** que una ya procesada en ese lote → idempotencia intencional.
- **`event_id` o `bank_transaction_id` repetidos en webhook:** respuesta idempotente **200** con flags de duplicado; no se crea segundo efecto sobre el pago.

---

### Tests automatizados

```bash
php artisan test
```

Opcional (estilo de código):

```bash
vendor/bin/pint --test
```

En CI/local la suite usa SQLite en memoria y cola **`sync`** (véase `phpunit.xml`).

---

### Colas en desarrollo

| `QUEUE_CONNECTION` | Uso |
|--------------------|-----|
| `sync` | Jobs en el mismo request (rápido para demos; así corre la suite de tests). |
| `database` | Persiste jobs; ejecute en paralelo **`php artisan queue:work`** para procesar `ProcessBankNotificationJob`, `NotifyPaymentConfirmedJob`, etc. |

---

### API implementada (resumen)

| Método | Ruta | Descripción |
|--------|------|-------------|
| POST | `/api/v1/payments` | Crea operación `PENDING`. Código `LTP-YYYYMMDD-######` (secuencia diaria, `APP_TIMEZONE`). Monto en centavos en BD (`amount_minor`). |
| POST | `/api/v1/bank/notifications` | Webhook (Bearer o HMAC). Idempotencia por `event_id` / `bank_transaction_id`. Job `ProcessBankNotificationJob` → `PAID` u `OBSERVED`. |
| POST | `/api/v1/bank/reconciliation` | Lote cierre: MATCHED / DISCREPANCY / UNMATCHED; confirmación tardía documentada en código y `docs/API.md`. |
| GET | `/api/v1/settlements/candidates` | Candidatos a liquidación (corte **20:45**, **America/Lima**). Query opc.: `as_of`, `merchant_id`. |

Detalle de payloads y ejemplos: **`docs/API.md`**.

---

### Base de datos: restricciones, índices e idempotencia

**Pagos (`payments`)**

- `payment_code` **UNIQUE**: garantiza identificador comercial único frente al banco y liquidaciones.
- Índices sobre `status`, `paid_at`, `reconciliation_match`, `(merchant_id, status)`, `created_at`: aceleran listados por estado, informes y filtros de comercio.
- `settled_at`: marca opcional de operación ya considerada en liquidación (consulta de candidatos la excluye cuando está definida).

**Secuencia diaria (`payment_sequences`)**

- `sequence_date` **UNIQUE** + `last_serial`: evita condiciones de carrera al numerar `LTP-…` por día calendario en Lima.

**Notificaciones banco (`bank_notifications`)**

- `event_id` **UNIQUE**: no reprocesar el mismo evento del proveedor.
- `bank_transaction_id` **UNIQUE**: no duplicar la misma transacción bancaria en dos pagos distintos (política estricta del caso).
- Índices en `payment_code`, resultados de proceso: trazabilidad y soporte operativo.

**Conciliación**

- `bank_reconciliation_batches`: **UNIQUE (`bank`, `process_date`)** — un lote por banco y fecha de proceso.
- `bank_reconciliation_movements`: **UNIQUE (`bank_reconciliation_batch_id`, `bank_movement_id`)** (`reconciliation_batch_movement_unique`) — no duplicar líneas dentro del mismo archivo/lote.

**Notificaciones externas (`external_notifications`)**

- `payment_id` **UNIQUE**: una fila de seguimiento por pago; evita dobles envíos exitosos no controlados.

**Idempotencia (resumen operativo)**

- Doble `event_id` → respuesta idempotente sin nuevo efecto en el pago.
- Doble `bank_transaction_id` distinto `event_id` → rechazado por restricción de negocio / unicidad.
- Doble `bank_movement_id` en el mismo lote de conciliación → segunda línea omitida (resumen en respuesta).

---

### Notificación externa al pasar a PAID (módulo E)

Tras confirmar **`PAID`** (webhook o confirmación tardía), se encola **`NotifyPaymentConfirmedJob`** cuando corresponde, fuera de la transacción principal del banco. Implementación por defecto: **`FakePaymentNotificationClient`** (contrato `PaymentNotificationClient`). Persistencia en **`external_notifications`** (`attempt_count`, estados, snapshot del payload). Sustituir el binding en `AppServiceProvider` por un cliente HTTP real en producción (timeouts, reintentos, circuit breaker).

---

### Variables de entorno relevantes

| Variable | Rol |
|----------|-----|
| `APP_TIMEZONE` | Zona horaria del negocio (`America/Lima` recomendado). |
| `BANK_WEBHOOK_SECRET` | Secreto compartido webhook (Bearer token literal o clave HMAC). |
| `QUEUE_CONNECTION` | `sync` vs `database` / Redis según entorno. |
| `PAYMENT_NOTIFICATION_SIMULATE_ERROR` | Simula fallo transitorio en el primer intento del cliente fake. |

---

### Marco Laravel

Plantilla estándar Laravel 12. Documentación del framework: [laravel.com/docs](https://laravel.com/docs).
