# chatme-wa-bridge

Microservicio Node.js que conecta Baileys (WhatsApp Web protocol) con Laravel vía Redis pub/sub.

Fase 22 — WhatsApp Directo.

## Arquitectura

```
[Laravel App] ←→ Redis pub/sub ←→ [este servicio: Baileys]
                                        ↓
                                  sessions/{channel_id}/
```

Canales Redis:

- `wa:outbound:{channel_id}` — Laravel → Node (enviar mensaje, iniciar pairing, etc.)
- `wa:inbound:{channel_id}` — Node → Laravel (mensajes entrantes, media, acks)
- `wa:status:{channel_id}` — Node → Laravel (QR, conectado, desconectado, errores)

El `{channel_id}` es el `channels.id` de Laravel.

## Instalación

```bash
cd chatme-wa-bridge
npm install
cp .env.example .env
# editar .env si tu Redis no está en 127.0.0.1:6379
npm start
```

## Desarrollo

```bash
npm run dev   # reinicia en cada cambio de archivo
```

## Mensajes soportados (contrato con Laravel)

### Outbound (Laravel → Node)

```json
// Iniciar pairing (mostrar QR)
{ "type": "pair", "ref": "uuid-opcional" }

// Enviar texto
{ "type": "send_text", "to": "5215512345678", "text": "hola", "ref": "msg-uuid" }

// Cerrar sesión
{ "type": "logout", "ref": "uuid" }
```

### Inbound (Node → Laravel)

```json
// Mensaje de texto entrante
{ "event": "message", "from": "5215512345678", "text": "hola", "msgId": "wa-msg-id" }

// Ack de envío (sent, delivered, read)
{ "event": "ack", "msgId": "wa-msg-id", "status": "delivered", "ref": "msg-uuid" }
```

### Status (Node → Laravel)

```json
{ "event": "qr", "qr": "data:image/png;base64,..." }
{ "event": "connected", "me": "5215512345678" }
{ "event": "disconnected", "reason": "logout" }
{ "event": "send_failed", "error": "...", "ref": "msg-uuid" }
```

## Carpetas

- `src/` — código fuente
- `sessions/` — auth state persistido por Baileys (NUNCA commit a git)

## Restricciones

- Solo uso conversacional (responder a quien escribe). NO broadcast ni campañas.
- Feature gates en Laravel bloquean este tipo de canal en features masivas.
