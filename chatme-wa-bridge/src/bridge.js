import logger from './logger.js';
import { subscriber, publisher, channels, patterns, extractChannelId, publish } from './redis.js';

const handlers = {
    onOutbound: null,
};

export function setHandlers({ onOutbound }) {
    handlers.onOutbound = onOutbound;
}

export async function start() {
    await subscriber.psubscribe(patterns.outbound);
    logger.info({ pattern: patterns.outbound }, 'subscribed to outbound pattern');

    subscriber.on('pmessage', async (_pattern, channel, message) => {
        const channelId = extractChannelId(channel);
        let payload;
        try {
            payload = JSON.parse(message);
        } catch (err) {
            logger.error({ err, message }, 'invalid JSON on outbound');
            return;
        }

        logger.info({ channelId, type: payload.type }, 'outbound received');

        if (handlers.onOutbound) {
            try {
                await handlers.onOutbound(channelId, payload);
            } catch (err) {
                logger.error({ err, channelId, payload }, 'outbound handler failed');
                await emitStatus(channelId, { event: 'send_failed', error: err.message, ref: payload.ref });
            }
        } else {
            // POC echo: sin Baileys todavía, solo confirmamos ida/vuelta
            await publish(channels(channelId).inbound, {
                event: 'echo',
                original: payload,
            });
        }
    });
}

export async function emitInbound(channelId, payload) {
    await publish(channels(channelId).inbound, payload);
}

export async function emitStatus(channelId, payload) {
    await publish(channels(channelId).status, payload);
}

export async function shutdown() {
    logger.info('shutting down bridge');
    await subscriber.quit();
    await publisher.quit();
}
