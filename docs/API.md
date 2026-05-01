# API — Guía para revisores y pruebas manuales

Este documento complementa el [`README.md`](../README.md) del raíz. Describe cómo ejecutar la aplicación, probar cada endpoint con herramientas habituales (curl, Postman) y qué respuestas esperar.

## Prerrequisitos

- PHP **8.2+** y **Composer**.
- Extensiones habituales de Laravel (pdo, openssl, mbstring, tokenizer, xml, ctype, json, etc.).
- Base de datos: el proyecto funciona con **MySQL** (ajustar `DB_*` en `.env`).

## Puesta en marcha

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed
```

Configure al menos:

| Variable | Uso |
|----------|-----|
| `APP_TIMEZONE` | Debe ser `America/Lima` para alinear códigos de pago, liquidación y cortes (valor por defecto en ejemplo). |
| `BANK_WEBHOOK_SECRET` | Secreto compartido para `POST /api/v1/bank/*` (Bearer o HMAC). |
| `QUEUE_CONNECTION` | `sync` para pruebas rápidas sin worker; `database` si quiere ver jobs asíncronos reales. |

Levante el servidor HTTP:

```bash
php artisan serve
```

Por defecto la base URL es `http://127.0.0.1:8000`.

Tras el seed existen comercios (`merchants`); el **`merchant_id = 1`** suele ser válido. Si recibe error de validación en creación de pagos, ejecute `php artisan db:seed` o consulte `SELECT id FROM merchants LIMIT 5`.

---

## Importar en Postman / Insomnia

1. **Postman:** archivo [`postman/Latinpay-Challenge.postman_collection.json`](postman/Latinpay-Challenge.postman_collection.json).
2. Opcional: entorno [`postman/Latinpay-Challenge.postman_environment.json`](postman/Latinpay-Challenge.postman_environment.json) — ajuste `base_url` y `webhook_secret` igual que en `.env`.

En Insomnia puede importar la colección Postman (Import → From File) o recrear las peticiones copiando URLs y cuerpos de esta guía.

---

## Autenticación de webhooks bancarios

Rutas:

- `POST /api/v1/bank/notifications`
- `POST /api/v1/bank/reconciliation`

**Opción A — Bearer (recomendada para pruebas):**

```http
Authorization: Bearer <mismo valor que BANK_WEBHOOK_SECRET>
Content-Type: application/json
Accept: application/json
```

**Opción B — HMAC:** cabecera `X-Bank-Signature` con el valor **hexadecimal** de `HMAC-SHA256(cuerpo_crudo_UTF8, secreto)` donde `secreto` es `BANK_WEBHOOK_SECRET`.

Ejemplo para obtener la firma con PHP (el primer argumento debe ser **exactamente** la cadena del body que enviará):

```bash
BODY='{"event_id":"evt_001","bank_transaction_id":"bank_tx_999","payment_code":"LTP-20260424-000001","amount":150.50,"currency":"PEN","status":"PAID","paid_at":"2026-04-24 20:44:00"}'
php -r 'echo hash_hmac("sha256", $argv[1], $argv[2]);' "$BODY" "su-secreto-aqui"
```

Copie la salida hexadecimal en la cabecera `X-Bank-Signature` y envíe el mismo `$BODY` como cuerpo raw (sin espacios extra).

---

## Flujo manual recomendado (happy path)

Sustituya la URL base si usa otro host/puerto.

### 1. Crear pago (`PENDING`)

```bash
curl -s -X POST http://127.0.0.1:8000/api/v1/payments \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d "{\"merchant_id\":1,\"customer_document\":\"76359665\",\"amount\":150.50,\"currency\":\"PEN\",\"description\":\"Demo revisión\"}"
```

Respuesta esperada: **201** con `payment_code`, `status: PENDING`, `amount`, `currency`. **Guarde `payment_code`** para los siguientes pasos.

### 2. Confirmación bancaria en tiempo real

Use un `event_id` y `bank_transaction_id` **únicos** por intento (la BD los trata como únicos globales).

```bash
curl -s -X POST http://127.0.0.1:8000/api/v1/bank/notifications \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer SU_SECRETO_WEBHOOK" \
  -d "{\"event_id\":\"evt_review_001\",\"bank_transaction_id\":\"bank_tx_review_001\",\"payment_code\":\"PEGUE_AQUI_EL_CODIGO\",\"amount\":150.50,\"currency\":\"PEN\",\"status\":\"PAID\",\"paid_at\":\"2026-04-24 20:44:00\"}"
```

Respuesta esperada: **202** con `status` de tipo encolado (`QUEUED` según implementación). Si `QUEUE_CONNECTION=sync`, el pago pasará a **`PAID`** de inmediato.

Compruebe en BD o repitiendo un GET de candidatos (paso 4) que el estado sea coherente.

**Idempotencia:** repita el mismo JSON → debe responder indicando duplicado de `event_id` sin reprocesar.

### 3. Conciliación de cierre

El movimiento debe ser coherente con el escenario (mismo `payment_code`, montos y — si aplica — `bank_transaction_id` que exista en notificación previa para match en tiempo real).

```bash
curl -s -X POST http://127.0.0.1:8000/api/v1/bank/reconciliation \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer SU_SECRETO_WEBHOOK" \
  -d "{\"bank\":\"BANK_A\",\"process_date\":\"2026-04-24\",\"movements\":[{\"bank_movement_id\":\"mov_review_001\",\"bank_transaction_id\":\"bank_tx_review_001\",\"payment_code\":\"PEGUE_AQUI_EL_CODIGO\",\"amount\":150.50,\"currency\":\"PEN\",\"paid_at\":\"2026-04-24 20:44:30\"}]}"
```

Respuesta esperada: **200** con resumen (`matched`, `discrepancy`, `unmatched`, etc.).

### 4. Candidatos a liquidación

```bash
curl -s "http://127.0.0.1:8000/api/v1/settlements/candidates?as_of=2026-04-27&merchant_id=1" \
  -H "Accept: application/json"
```

La fecha `as_of` es la fecha de valoración en **America/Lima**. Solo aparecen operaciones cuya **primera fecha hábil elegible** sea menor o igual a `as_of` (reglas de corte **20:45** descritas en el README).

Respuesta: objeto con `as_of`, `timezone`, `cutoff_time`, `candidates[]` (cada ítem incluye `settlement_eligible_from`).

---

## Referencia rápida de endpoints

| Método | Ruta | Auth |
|--------|------|------|
| POST | `/api/v1/payments` | No |
| POST | `/api/v1/bank/notifications` | Webhook |
| POST | `/api/v1/bank/reconciliation` | Webhook |
| GET | `/api/v1/settlements/candidates` | No |

### Crear pago

- **Body:** `merchant_id` (int), `customer_document`, `amount` (decimal ≤2 decimales), `currency` (`PEN`), `description` opcional.
- **Errores:** **422** validación (p. ej. `merchant_id` inexistente).

### Webhook notificación

- **Body:** `event_id`, `bank_transaction_id`, `payment_code`, `amount`, `currency`, `status`, `paid_at`.
- **Errores:** **401** secreto inválido; **422** validación; **200** con flags de duplicado cuando corresponda.

### Conciliación

- **Body:** `bank`, `process_date` (`Y-m-d`), `movements[]` con `bank_movement_id`, `amount`, `currency`, y opcionalmente `bank_transaction_id`, `payment_code`, `paid_at`.

### Candidatos liquidación

- **Query:** `as_of` opcional (`Y-m-d`), `merchant_id` opcional.

---

## Colas (`QUEUE_CONNECTION`)

| Valor | Comportamiento |
|-------|----------------|
| `sync` | Los jobs se ejecutan en el mismo request (adecuado para demos y la suite de tests PHPUnit). |
| `database` | Los jobs se guardan en la tabla `jobs`. Deje otro terminal con `php artisan queue:work` mientras prueba webhooks. |

Tras migraciones, Laravel crea también tablas relacionadas con jobs/failed jobs según configuración.

---

## Tests automatizados

Desde la raíz del proyecto:

```bash
php artisan test
```

Cubre creación de pagos, webhook (incl. idempotencia y OBSERVED), conciliación, notificación externa simulada y candidatos a liquidación.

---

## Problemas frecuentes

| Síntoma | Causa probable |
|---------|----------------|
| **405** en `/api/v1/payments` | Se usó **GET**; la creación es solo **POST**. |
| Página HTML “welcome” en rutas API | URL incorrecta; las rutas están bajo **`/api/v1/...`**. |
| `merchant_id` inválido | Falta `php artisan db:seed` o usar un id existente en `merchants`. |
| Webhook **401** | `Authorization` no coincide con `BANK_WEBHOOK_SECRET` o HMAC mal calculado sobre el body exacto. |
| Pago no pasa a PAID con cola `database` | No hay worker: ejecute `php artisan queue:work`. |
| `bank_transaction_id` duplicado | Debe generar un id nuevo por cada notificación de prueba (restricción UNIQUE). |

---

## Soporte

Para el alcance funcional y decisiones de diseño, véase [`CHALLENGE_REQUIREMENTS.md`](CHALLENGE_REQUIREMENTS.md).
