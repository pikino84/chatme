import Redis from 'ioredis';
import logger from './logger.js';

const config = {
    host: process.env.REDIS_HOST || '127.0.0.1',
    port: parseInt(process.env.REDIS_PORT || '6379', 10),
    password: process.env.REDIS_PASSWORD || undefined,
    db: parseInt(process.env.REDIS_DB || '0', 10),
    lazyConnect: false,
    maxRetriesPerRequest: null,
};

export const PREFIX = process.env.WA_BRIDGE_PREFIX || 'wa';

export const publisher = new Redis(config);
export const subscriber = new Redis(config);

publisher.on('error', (err) => logger.error({ err }, 'redis publisher error'));
subscriber.on('error', (err) => logger.error({ err }, 'redis subscriber error'));
publisher.on('connect', () => logger.info('redis publisher connected'));
subscriber.on('connect', () => logger.info('redis subscriber connected'));

export function channels(channelId) {
    return {
        outbound: `${PREFIX}:outbound:${channelId}`,
        inbound: `${PREFIX}:inbound:${channelId}`,
        status: `${PREFIX}:status:${channelId}`,
    };
}

export const patterns = {
    outbound: `${PREFIX}:outbound:*`,
};

export function extractChannelId(channel) {
    const parts = channel.split(':');
    return parts[parts.length - 1];
}

export async function publish(channel, payload) {
    const msg = JSON.stringify({ ...payload, ts: Date.now() });
    await publisher.publish(channel, msg);
}
