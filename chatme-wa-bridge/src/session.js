import { makeWASocket, useMultiFileAuthState, DisconnectReason, fetchLatestBaileysVersion } from '@whiskeysockets/baileys';
import { Boom } from '@hapi/boom';
import path from 'node:path';
import fs from 'node:fs/promises';
import { fileURLToPath } from 'node:url';
import logger from './logger.js';
import { emitInbound, emitStatus } from './bridge.js';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const SESSIONS_ROOT = path.resolve(__dirname, '..', process.env.SESSIONS_DIR || 'sessions');

const sessions = new Map();

function sessionFolder(channelId) {
    return path.join(SESSIONS_ROOT, String(channelId));
}

function jidFor(phone) {
    const clean = String(phone).replace(/\D/g, '');
    return `${clean}@s.whatsapp.net`;
}

function phoneFromJid(jid) {
    if (!jid) return null;
    return jid.split('@')[0].split(':')[0];
}

function extractText(message) {
    if (!message) return null;
    return (
        message.conversation ||
        message.extendedTextMessage?.text ||
        message.imageMessage?.caption ||
        message.videoMessage?.caption ||
        message.documentMessage?.caption ||
        null
    );
}

function mediaKind(message) {
    if (!message) return null;
    if (message.imageMessage) return 'image';
    if (message.videoMessage) return 'video';
    if (message.audioMessage) return 'audio';
    if (message.documentMessage) return 'document';
    if (message.stickerMessage) return 'sticker';
    if (message.locationMessage) return 'location';
    if (message.contactMessage) return 'contact';
    return null;
}

export async function startSession(channelId, { resetAuth = false } = {}) {
    const existing = sessions.get(channelId);
    if (existing && !resetAuth) {
        logger.info({ channelId }, 'session already exists, skipping start');
        return existing;
    }

    const folder = sessionFolder(channelId);
    if (resetAuth) {
        await fs.rm(folder, { recursive: true, force: true });
    }
    await fs.mkdir(folder, { recursive: true });

    const { state, saveCreds } = await useMultiFileAuthState(folder);
    const { version } = await fetchLatestBaileysVersion();

    const sock = makeWASocket({
        version,
        auth: state,
        printQRInTerminal: false,
        logger: logger.child({ channelId, scope: 'baileys' }),
        browser: ['ChatMe', 'Chrome', '1.0'],
        syncFullHistory: false,
        generateHighQualityLinkPreview: false,
        markOnlineOnConnect: false,
    });

    const record = { sock, channelId, folder, connected: false };
    sessions.set(channelId, record);

    sock.ev.on('creds.update', saveCreds);

    sock.ev.on('connection.update', async (update) => {
        const { connection, lastDisconnect, qr } = update;

        if (qr) {
            logger.info({ channelId }, 'QR generated');
            await emitStatus(channelId, { event: 'qr', qr });
        }

        if (connection === 'open') {
            record.connected = true;
            const me = phoneFromJid(sock.user?.id);
            logger.info({ channelId, me }, 'connection open');
            await emitStatus(channelId, { event: 'connected', me, name: sock.user?.name || null });
        }

        if (connection === 'close') {
            record.connected = false;
            const code = new Boom(lastDisconnect?.error)?.output?.statusCode;
            const loggedOut = code === DisconnectReason.loggedOut;
            logger.warn({ channelId, code, loggedOut }, 'connection closed');

            await emitStatus(channelId, {
                event: loggedOut ? 'logged_out' : 'disconnected',
                code,
                reason: lastDisconnect?.error?.message || null,
            });

            sessions.delete(channelId);

            if (loggedOut) {
                await fs.rm(folder, { recursive: true, force: true });
            } else {
                setTimeout(() => {
                    startSession(channelId).catch((err) =>
                        logger.error({ err, channelId }, 'reconnect failed'),
                    );
                }, 3000);
            }
        }
    });

    sock.ev.on('messages.upsert', async ({ messages, type }) => {
        if (type !== 'notify') return;
        for (const msg of messages) {
            if (!msg.message) continue;
            if (msg.key.fromMe) continue;
            if (msg.key.remoteJid?.endsWith('@g.us')) continue; // ignora grupos por ahora

            const from = phoneFromJid(msg.key.remoteJid);
            const text = extractText(msg.message);
            const media = mediaKind(msg.message);

            await emitInbound(channelId, {
                event: 'message',
                msgId: msg.key.id,
                from,
                pushName: msg.pushName || null,
                text,
                media,
                timestamp: msg.messageTimestamp,
                raw: null,
            });
        }
    });

    sock.ev.on('messages.update', async (updates) => {
        for (const u of updates) {
            if (!u.update.status) continue;
            await emitInbound(channelId, {
                event: 'ack',
                msgId: u.key.id,
                status: u.update.status, // 1=sent, 2=delivered, 3=read (Baileys enum)
            });
        }
    });

    logger.info({ channelId, folder }, 'session started');
    return record;
}

export async function sendText(channelId, to, text, ref) {
    const record = sessions.get(channelId);
    if (!record || !record.connected) {
        throw new Error(`session ${channelId} not connected`);
    }
    const result = await record.sock.sendMessage(jidFor(to), { text });
    return { msgId: result?.key?.id, ref };
}

export async function logout(channelId) {
    const record = sessions.get(channelId);
    if (!record) return;
    try {
        await record.sock.logout();
    } catch (err) {
        logger.warn({ err, channelId }, 'logout error (forcing cleanup)');
    }
    sessions.delete(channelId);
    await fs.rm(sessionFolder(channelId), { recursive: true, force: true });
}

export function listSessions() {
    return Array.from(sessions.keys()).map((id) => ({
        channelId: id,
        connected: sessions.get(id).connected,
    }));
}

export async function restoreAll() {
    try {
        await fs.mkdir(SESSIONS_ROOT, { recursive: true });
        const entries = await fs.readdir(SESSIONS_ROOT);
        for (const entry of entries) {
            const stat = await fs.stat(path.join(SESSIONS_ROOT, entry));
            if (!stat.isDirectory()) continue;
            logger.info({ channelId: entry }, 'restoring session from disk');
            await startSession(entry).catch((err) =>
                logger.error({ err, channelId: entry }, 'restore failed'),
            );
        }
    } catch (err) {
        logger.error({ err }, 'restoreAll failed');
    }
}
