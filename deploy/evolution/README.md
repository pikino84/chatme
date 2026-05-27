# Evolution API — WhatsApp Directo (Phase 22)

Evolution API es el gateway de WhatsApp que reemplaza al microservicio
`chatme-wa-bridge` (Baileys custom). ChatMe habla con él por HTTP y recibe los
eventos entrantes por webhook.

```
[ChatMe Laravel]  --HTTP REST-->  [Evolution API]  <--Baileys-->  WhatsApp
       ^                                |
       └──────── webhook POST ──────────┘
```

Cada `channel` de tipo `whatsapp_web` corresponde a una **instancia** de
Evolution llamada `chatme-ch-{channel_id}`.

---

## 1. Levantar Evolution (local / dev)

```sh
cd deploy/evolution
cp .env.example .env        # ya hay un .env con valores de dev generados
docker compose up -d
docker compose logs -f evolution-api   # esperar "started" sin errores
```

Evolution queda en `http://localhost:8085` (el 8080 lo usa Reverb).
Panel/manager: `http://localhost:8085/manager`.

## 2. Variables en el `.env` de ChatMe

```
EVOLUTION_API_URL=http://localhost:8085
EVOLUTION_API_KEY=<misma AUTHENTICATION_API_KEY del .env de Evolution>
EVOLUTION_WEBHOOK_URL=http://host.docker.internal:8000/api/webhooks/evolution
EVOLUTION_WEBHOOK_TOKEN=<token compartido cualquiera>
```

Tras editar el `.env`: `php artisan config:clear`.

## 3. Webhook: que Evolution alcance a Laravel (local)

El contenedor de Evolution debe poder hacer `POST` al endpoint de ChatMe.
La forma más confiable en local es servir Laravel con un puerto fijo (ignora
el `Host` header, a diferencia del vhost de Apache):

```sh
php artisan serve --host=0.0.0.0 --port=8000
```

Entonces `EVOLUTION_WEBHOOK_URL=http://host.docker.internal:8000/api/webhooks/evolution`.

> Alternativa: añadir `host.docker.internal` como `ServerAlias` del vhost de
> Laragon y usar `EVOLUTION_WEBHOOK_URL=http://host.docker.internal/api/webhooks/evolution`.

## 4. Probar

1. App → **WhatsApp Directo** → *Vincular nuevo WhatsApp*.
2. Escanear el QR con el celular (Ajustes → Dispositivos vinculados).
3. Al conectar, la sesión pasa a `connected` y se abre el chat.

---

## Producción (VPS Contabo)

1. `cd /var/www/chatme.com.mx/deploy/evolution`
2. `cp .env.example .env` y rellenar con secretos de producción.
   `SERVER_URL` = URL pública del gateway (ej. `https://wa.chatme.com.mx`).
3. `docker compose up -d`
4. En el `.env` de ChatMe:
   - `EVOLUTION_API_URL` = URL interna del gateway
   - `EVOLUTION_WEBHOOK_URL=https://app.chatme.com.mx/api/webhooks/evolution`
   - `EVOLUTION_API_KEY` / `EVOLUTION_WEBHOOK_TOKEN` = secretos de producción
5. `php artisan config:cache`
6. Re-vincular cada número escaneando el QR de nuevo (las sesiones del bridge
   antiguo **no** son portables a Evolution).
7. Una vez verificado, retirar Supervisor del bridge viejo:
   `supervisorctl stop chatme-wa-bridge chatme-wa-listener` y borrar sus configs.

## Notas

- Pinear `image: atendai/evolution-api:vX.Y.Z` a una versión concreta.
- Evolution guarda su estado en su propio Postgres + Redis (volúmenes Docker),
  aislado de la base de datos de ChatMe.
- El webhook se valida con el header `Authorization` = `EVOLUTION_WEBHOOK_TOKEN`.
