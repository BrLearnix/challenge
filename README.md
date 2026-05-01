## Prueba técnica Backend (Latinpay)

Solución en Laravel 12 / PHP 8.2+. Enunciado completo: [`docs/CHALLENGE_REQUIREMENTS.md`](docs/CHALLENGE_REQUIREMENTS.md).

### Arranque rápido

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed
```

Colas: con `QUEUE_CONNECTION=database` ejecuta `php artisan queue:work` para procesar jobs (p. ej. confirmaciones bancarias). En tests la cola es `sync`.

Configura `BANK_WEBHOOK_SECRET` en `.env`. El webhook acepta `Authorization: Bearer <secret>` o cabecera `X-Bank-Signature` con hex(`HMAC-SHA256(body_crudo, secret)`).

### API implementada

| Método | Ruta | Descripción |
|--------|------|-------------|
| POST | `/api/v1/payments` | Crea operación en estado `PENDING`. Código único `LTP-YYYYMMDD-######` (secuencia por día, zona `APP_TIMEZONE`). El body envía `amount` en soles con hasta 2 decimales; en BD se guarda en centavos (`amount_minor`) para precisión. |
| POST | `/api/v1/bank/notifications` | Webhook banco (auth Bearer o HMAC). Guarda payload, evita duplicados por `event_id` y `bank_transaction_id`, encola `ProcessBankNotificationJob` y actualiza el pago a `PAID` o `OBSERVED` con auditoría. |
| POST | `/api/v1/bank/reconciliation` | Cierre / lote por `bank` + `process_date`. Por movimiento: `MATCHED` si coincide con `PAID` y confirmación tiempo real (`bank_transaction_id`), `DISCREPANCY` si hay diferencias o falta match tiempo real, `UNMATCHED` si no existe `payment_code`. No reprocesa el mismo `bank_movement_id` en el lote. **Confirmación tardía:** si el pago sigue `PENDING` y monto/moneda coinciden, se marca `PAID` y luego `RECONCILED` y queda trazado en `payment_audits`. |
| GET | `/api/v1/settlements/candidates` | Lista pagos **elegibles para liquidación** según el Módulo F: solo `PAID` o `RECONCILED` sin `reconciliation_match = DISCREPANCY`, con `paid_at`, sin `settled_at`. Hora de corte **20:45** en **`America/Lima`** (`APP_TIMEZONE`): hasta esa hora (inclusive) el primer día hábil elegible es el siguiente al día del pago; después de 20:45 se aplica un día hábil adicional. Query opcional: `as_of=YYYY-MM-DD` (fecha de valoración; por defecto hoy en Lima), `merchant_id`. Respuesta incluye `settlement_eligible_from` por operación. **Día hábil:** solo se excluyen sábados y domingos; en producción convendría una tabla de feriados. |

### Notificación externa al pasar a PAID (módulo E)

Cuando un pago queda `PAID` (webhook banco o confirmación tardía en conciliación), se encola `NotifyPaymentConfirmedJob` **tras el commit** de la transacción principal. El contrato `PaymentNotificationClient` está enlazado en `AppServiceProvider` a `FakePaymentNotificationClient`, que solo registra un log; en producción se sustituiría por un cliente HTTP con URL, timeouts estrictos y política de reintentos acorde al proveedor.

La tabla `external_notifications` guarda un snapshot del payload, `attempt_count`, último error y estado (`PENDING` / `SENT` / `FAILED`). El job define `$tries` y `backoff()` para reintentos ante fallos transitorios.

Para **simular** un fallo en el primer intento (útil en desarrollo o tests manuales): `PAYMENT_NOTIFICATION_SIMULATE_ERROR=true` en `.env`.


---

<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework. You can also check out [Laravel Learn](https://laravel.com/learn), where you will be guided through building a modern Laravel application.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
