# Supervisor configs

Configuraciones para el servidor de producción (Contabo VPS Ubuntu).

## Servicios

| Archivo | Proceso | Función |
|---|---|---|
| `chatme-wa-bridge.conf` | Node (Baileys) | Mantiene la sesión WhatsApp abierta y traduce entre Redis y WhatsApp |
| `chatme-wa-listener.conf` | PHP (`php artisan wa:listen`) | Consume eventos del bridge vía Redis y persiste en DB |

Los dos trabajan en conjunto: sin el bridge no hay conexión a WhatsApp; sin el listener los mensajes entrantes se pierden (aunque el bridge los reciba).

Complementan a los dos servicios ya existentes:
- `chatme-worker.conf` — queues de Horizon (críticos, media, default)
- `reverb.conf` — WebSocket server

## Deploy (primera vez)

```bash
# En el servidor (/var/www/chatme.com.mx/)
git pull

# Instalar deps del bridge
cd chatme-wa-bridge
npm ci --omit=dev
cp .env.example .env
# editar .env si es necesario (REDIS_HOST, etc.)
cd ..

# Copiar configs
sudo cp deploy/supervisor/chatme-wa-bridge.conf /etc/supervisor/conf.d/
sudo cp deploy/supervisor/chatme-wa-listener.conf /etc/supervisor/conf.d/

# Activar
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start chatme-wa-bridge
sudo supervisorctl start chatme-wa-listener

# Verificar
sudo supervisorctl status | grep chatme-wa
```

## Primer pairing (producción)

Una vez el bridge arriba, crea un canal WhatsApp Directo desde la UI
(`/whatsapp-directo` → "Vincular nuevo número"), escanea el QR y listo.

Las sesiones se persisten en `chatme-wa-bridge/sessions/{channel_id}/`.
Agregar este folder al backup — si se pierde, hay que re-escanear todos
los QRs.

## Logs

```bash
# Live stream
sudo tail -f /var/log/supervisor/chatme-wa-bridge-stdout.log
sudo tail -f /var/log/supervisor/chatme-wa-listener-stdout.log
```

## Restart después de cambios en código

```bash
# Cambios en chatme-wa-bridge/src/*.js
sudo supervisorctl restart chatme-wa-bridge

# Cambios en app/Console/Commands/WhatsAppWebListener.php
sudo supervisorctl restart chatme-wa-listener
```

## Requisitos de Node en VPS

- Node 20+
- `cd chatme-wa-bridge && npm ci --omit=dev` (instala Baileys sin devDeps)
- `sessions/` folder writable por `www-data`

## Permisos

```bash
sudo chown -R www-data:www-data /var/www/chatme.com.mx/chatme-wa-bridge
```
