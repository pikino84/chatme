import Redis from 'ioredis';
import qrcode from 'qrcode';

const CHANNEL_ID = process.argv[2] || 'test-1';
const PREFIX = 'wa';

const sub = new Redis();
const pub = new Redis();

console.log(`\n[test-pair] Iniciando pairing para channel_id=${CHANNEL_ID}`);
console.log(`[test-pair] Escucha en ${PREFIX}:status:${CHANNEL_ID} y ${PREFIX}:inbound:${CHANNEL_ID}\n`);

await sub.subscribe(`${PREFIX}:status:${CHANNEL_ID}`, `${PREFIX}:inbound:${CHANNEL_ID}`);

sub.on('message', async (channel, raw) => {
    const payload = JSON.parse(raw);
    const kind = channel.includes(':status:') ? 'STATUS' : 'INBOUND';

    if (payload.event === 'qr') {
        console.log('\n[test-pair] QR recibido — escanea con WhatsApp (Dispositivos vinculados):\n');
        const ascii = await qrcode.toString(payload.qr, { type: 'terminal', small: true });
        console.log(ascii);
        console.log('[test-pair] Esperando conexión...\n');
        return;
    }

    if (payload.event === 'connected') {
        console.log(`\n[test-pair] ✅ CONECTADO como ${payload.me} (${payload.name || 'sin nombre'})`);
        console.log('[test-pair] Envíame un mensaje desde otro WhatsApp para probar inbound.');
        console.log('[test-pair] O escribe "send:5215512345678:hola" + Enter para probar outbound.\n');
        return;
    }

    console.log(`[${kind}]`, JSON.stringify(payload, null, 2));
});

// Permitir comandos por stdin: "send:to:text"
process.stdin.setEncoding('utf8');
process.stdin.on('data', async (line) => {
    const s = line.trim();
    if (s.startsWith('send:')) {
        const [, to, ...rest] = s.split(':');
        const text = rest.join(':');
        await pub.publish(`${PREFIX}:outbound:${CHANNEL_ID}`, JSON.stringify({
            type: 'send_text', to, text, ref: `test-${Date.now()}`,
        }));
        console.log(`[test-pair] → enviado a ${to}: "${text}"`);
    } else if (s === 'logout') {
        await pub.publish(`${PREFIX}:outbound:${CHANNEL_ID}`, JSON.stringify({ type: 'logout' }));
        console.log('[test-pair] → logout enviado');
    } else if (s === 'quit') {
        process.exit(0);
    }
});

// Dispara pairing
await pub.publish(`${PREFIX}:outbound:${CHANNEL_ID}`, JSON.stringify({ type: 'pair' }));
console.log('[test-pair] → pairing solicitado, esperando QR...');
