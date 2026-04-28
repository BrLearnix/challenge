# 03. Contrato de API

## A. Crear operación de pago

### Endpoint

POST /api/v1/payments

### Payload de ejemplo

```json
{
  "merchant_id": 10,
  "customer_document": "76359665",
  "amount": 150.50,
  "currency": "PEN",
  "description": "Pago de servicio mensual"
}
