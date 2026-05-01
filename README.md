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
