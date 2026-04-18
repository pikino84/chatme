import 'dotenv/config';
import logger from './logger.js';
import { start, shutdown, setHandlers, emitStatus } from './bridge.js';
import { startSession, sendText, logout, restoreAll } from './session.js';

async function handleOutbound(channelId, payload) {
    switch (payload.type) {
        case 'pair':
            await startSession(channelId, { resetAuth: payload.resetAuth === true });
            return;

        case 'send_text': {
            const { msgId } = await sendText(channelId, payload.to, payload.text, payload.ref);
            await emitStatus(channelId, {
                event: 'send_ok',
                ref: payload.ref,
                msgId,
            });
            return;
        }

        case 'logout':
            await logout(channelId);
            return;

        default:
            logger.warn({ channelId, type: payload.type }, 'unknown outbound type');
    }
}

async function main() {
    logger.info({
        node: process.version,
        pid: process.pid,
    }, 'chatme-wa-bridge starting');

    setHandlers({ onOutbound: handleOutbound });

    await start();
    await restoreAll();

    logger.info('bridge ready');
}

main().catch((err) => {
    logger.fatal({ err }, 'bridge crashed on startup');
    process.exit(1);
});

const handleSignal = async (signal) => {
    logger.info({ signal }, 'signal received');
    try {
        await shutdown();
    } catch (err) {
        logger.error({ err }, 'error during shutdown');
    }
    process.exit(0);
};

process.on('SIGTERM', handleSignal);
process.on('SIGINT', handleSignal);

process.on('uncaughtException', (err) => {
    logger.error({ err }, 'uncaughtException');
});
process.on('unhandledRejection', (err) => {
    logger.error({ err }, 'unhandledRejection');
});
