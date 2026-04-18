#!/usr/bin/env node
// Helper para renderizar un string de QR como ASCII en terminal.
// Uso: echo "<qr-raw>" | node qr-print.js
//      o: node qr-print.js "<qr-raw>"

import qrcode from 'qrcode';

async function render(input) {
    if (!input || !input.trim()) {
        console.error('qr-print: input vacío');
        process.exit(1);
    }
    const ascii = await qrcode.toString(input.trim(), { type: 'terminal', small: true });
    process.stdout.write(ascii);
}

const arg = process.argv[2];
if (arg) {
    render(arg);
} else {
    let buf = '';
    process.stdin.setEncoding('utf8');
    process.stdin.on('data', (chunk) => (buf += chunk));
    process.stdin.on('end', () => render(buf));
}
