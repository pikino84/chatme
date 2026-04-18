<?php

namespace App\Console\Commands;

use App\Models\Channel;
use App\Models\WhatsAppWebSession;
use Illuminate\Console\Command;

class WhatsAppWebQr extends Command
{
    protected $signature = 'wa:qr {channel : Channel ID} {--wait=30 : seconds to wait for QR}';
    protected $description = 'Print the last QR for a WhatsApp Web channel as ASCII (scan from terminal)';

    public function handle(): int
    {
        $channelId = (int) $this->argument('channel');
        $channel = Channel::withoutGlobalScopes()->find($channelId);
        if (!$channel) {
            $this->error("Channel #{$channelId} not found");
            return self::FAILURE;
        }

        $wait = (int) $this->option('wait');
        $deadline = time() + $wait;

        $session = null;
        while (time() < $deadline) {
            $session = WhatsAppWebSession::where('channel_id', $channel->id)->first();
            if ($session && $session->status === WhatsAppWebSession::STATUS_CONNECTED) {
                $this->info("Already connected as {$session->connected_phone} — no QR needed.");
                return self::SUCCESS;
            }
            if ($session && $session->qr_raw) {
                break;
            }
            usleep(500_000);
        }

        if (!$session || !$session->qr_raw) {
            $this->error('No QR available. Did you run `php artisan wa:pair ' . $channel->id . '` and is wa:listen running?');
            return self::FAILURE;
        }

        $this->line('');
        $this->line('Escanea con WhatsApp (Ajustes → Dispositivos vinculados → Vincular dispositivo):');
        $this->line('');
        $this->line($this->renderQrAscii($session->qr_raw));
        $this->line('');
        $this->info('QR generado: ' . ($session->qr_generated_at?->diffForHumans() ?? 'n/a'));

        return self::SUCCESS;
    }

    private function renderQrAscii(string $data): string
    {
        $bridge = base_path('chatme-wa-bridge/qr-print.js');

        if (!file_exists($bridge)) {
            return "(QR raw):\n{$data}";
        }

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $process = proc_open(['node', $bridge], $descriptors, $pipes, base_path('chatme-wa-bridge'));
        if (!is_resource($process)) {
            return "(QR raw):\n{$data}";
        }

        fwrite($pipes[0], $data);
        fclose($pipes[0]);
        $out = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);

        return $out !== false && $out !== '' ? $out : "(QR raw):\n{$data}";
    }
}
