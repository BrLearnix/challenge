# Backend Technical Challenge

Este repositorio contiene la prueba técnica para el proceso de selección de Desarrollador Backend Semi Senior.

El reto simula un flujo transaccional relacionado con pagos, confirmaciones bancarias en tiempo real, conciliación de cierre, procesamiento asincrónico, idempotencia, trazabilidad y consulta de operaciones candidatas a liquidación.

## Objetivo

Evaluar criterio real de desarrollo backend, especialmente en:

- Laravel / PHP moderno.
- Diseño de APIs REST.
- Jobs y queues.
- Procesamiento de notificaciones bancarias.
- Idempotencia.
- Conciliación bancaria.
- Modelado de base de datos.
- Índices y restricciones.
- Trazabilidad.
- Integración externa simulada.
- Tests automatizados.
- Documentación técnica.

## Tiempo esperado

La prueba está pensada para resolverse en aproximadamente 8 horas efectivas para un perfil semi senior.

No buscamos una solución innecesariamente extensa ni un sistema completo en producción. Buscamos una implementación clara, ordenada y técnicamente bien sustentada.

## Documentación del reto

Antes de iniciar, revisar los siguientes documentos:

1. [Contexto de negocio](docs/01-business-context.md)
2. [Requerimientos técnicos](docs/02-technical-requirements.md)
3. [Contrato de API](docs/03-api-contract.md)
4. [Criterios de evaluación](docs/04-evaluation-rubric.md)
5. [Guía de entrega](docs/05-submission-guide.md)
6. [Guía de presentación técnica](docs/06-presentation-guide.md)

## Entrega

El candidato deberá entregar su solución mediante un repositorio privado de GitHub o, en caso de inconveniente, mediante un archivo comprimido.

La solución debe incluir:

- Código fuente.
- README de instalación y ejecución.
- Archivo `.env.example`.
- Migraciones.
- Tests automatizados.
- Documentación breve de endpoints o colección Postman/Insomnia.
- Explicación de cómo ejecutar el queue worker.
- Explicación de decisiones de base de datos, índices e idempotencia.
- Explicación de la integración externa simulada.

## Importante

Se valorará más una solución simple, clara, segura y bien sustentada que una solución extensa pero desordenada.

El candidato deberá poder explicar su solución en una entrevista técnica posterior.
