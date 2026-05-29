<?php

namespace App\Services;

use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Parsea el archivo `_chat.txt` de una exportación de WhatsApp (Business o normal)
 * para el importador de historial de WhatsApp Directo (Fase 22.6).
 *
 * Soporta los dos formatos de export:
 *  - iOS:     `[12/04/2023, 14:32:10] Juan Pérez: Hola`
 *  - Android: `12/04/2023, 14:32 - Juan Pérez: Hola`
 *
 * Maneja mensajes multilínea, referencias a multimedia adjunta y mensajes de
 * sistema (que se descartan). NO toca la base de datos: solo convierte texto en
 * una lista estructurada que el controlador persiste.
 */
class WhatsAppChatImportService
{
    /** Marcas Unicode de dirección (LRM/RLM/LRE…) que WhatsApp inserta. */
    private const DIRECTION_MARKS = ["\u{200e}", "\u{200f}", "\u{202a}", "\u{202c}", "\u{2068}", "\u{2069}"];

    /**
     * Convierte el contenido de `_chat.txt` en una lista de mensajes.
     *
     * @param  bool  $dayFirst  true = formato DD/MM (locale MX/ES); false = MM/DD (US)
     * @return array<int, array{ts: ?CarbonInterface, sender: string, body: string, media_file: ?string, is_media_omitted: bool}>
     */
    public function parse(string $content, bool $dayFirst = true): array
    {
        // Normaliza saltos de línea y BOM.
        $content = preg_replace('/^\x{FEFF}/u', '', $content);
        $content = str_replace(["\r\n", "\r"], "\n", $content);
        $lines = explode("\n", $content);

        $messages = [];
        $current = null;

        foreach ($lines as $rawLine) {
            $line = $this->stripMarks($rawLine);
            $parsed = $this->matchLineStart($line);

            if ($parsed === null) {
                // Continuación multilínea del mensaje anterior.
                if ($current !== null) {
                    $current['body'] .= "\n" . $rawLine;
                }
                continue;
            }

            // Nueva línea con fecha: cierra el mensaje anterior.
            if ($current !== null) {
                $messages[] = $this->finalizeMessage($current);
            }

            [$dateStr, $timeStr, $remainder] = $parsed;
            $senderSplit = $this->splitSender($remainder);

            if ($senderSplit === null) {
                // Mensaje de sistema (sin "Nombre:"): se descarta.
                $current = null;
                continue;
            }

            $current = [
                'ts' => $this->parseTimestamp($dateStr, $timeStr, $dayFirst),
                'sender' => $senderSplit[0],
                'body' => $senderSplit[1],
            ];
        }

        if ($current !== null) {
            $messages[] = $this->finalizeMessage($current);
        }

        return $messages;
    }

    /**
     * Resumen para la pantalla de análisis (sin persistir).
     *
     * @param  array<int, array>  $messages
     * @return array{total:int, senders:array<string,int>, media_count:int, first_ts:?string, last_ts:?string}
     */
    public function summarize(array $messages): array
    {
        $senders = [];
        $mediaCount = 0;
        $first = null;
        $last = null;

        foreach ($messages as $m) {
            $senders[$m['sender']] = ($senders[$m['sender']] ?? 0) + 1;
            if ($m['media_file']) {
                $mediaCount++;
            }
            if ($m['ts']) {
                if ($first === null || $m['ts']->lt($first)) {
                    $first = $m['ts'];
                }
                if ($last === null || $m['ts']->gt($last)) {
                    $last = $m['ts'];
                }
            }
        }

        arsort($senders);

        return [
            'total' => count($messages),
            'senders' => $senders,
            'media_count' => $mediaCount,
            'first_ts' => $first?->toIso8601String(),
            'last_ts' => $last?->toIso8601String(),
        ];
    }

    /** Tipo de multimedia (image/video/audio/sticker/document) por extensión/nombre. */
    public function mediaTypeFor(string $fileName): string
    {
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $base = strtoupper($fileName);

        if (str_starts_with($base, 'STK') || ($ext === 'webp' && str_contains($base, 'STICKER'))) {
            return 'sticker';
        }

        return match (true) {
            in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'heic'], true) => 'image',
            in_array($ext, ['mp4', '3gp', 'mov', 'mkv', 'avi', 'webm'], true) => 'video',
            in_array($ext, ['opus', 'ogg', 'mp3', 'm4a', 'aac', 'amr', 'wav'], true) => 'audio',
            default => 'document',
        };
    }

    /** MIME aproximado por extensión (para guardar el adjunto). */
    public function mimeFor(string $fileName): string
    {
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        return match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'bmp' => 'image/bmp',
            'heic' => 'image/heic',
            'mp4' => 'video/mp4',
            '3gp' => 'video/3gpp',
            'mov' => 'video/quicktime',
            'mkv' => 'video/x-matroska',
            'avi' => 'video/x-msvideo',
            'webm' => 'video/webm',
            'opus' => 'audio/ogg',
            'ogg' => 'audio/ogg',
            'mp3' => 'audio/mpeg',
            'm4a' => 'audio/mp4',
            'aac' => 'audio/aac',
            'amr' => 'audio/amr',
            'wav' => 'audio/wav',
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            default => 'application/octet-stream',
        };
    }

    // --- Internos -----------------------------------------------------------

    private function stripMarks(string $line): string
    {
        return str_replace(self::DIRECTION_MARKS, '', $line);
    }

    /**
     * Detecta el prefijo de fecha/hora de una línea (iOS o Android).
     *
     * @return array{0:string,1:string,2:string}|null  [fecha, hora, resto] o null si no es inicio de mensaje
     */
    private function matchLineStart(string $line): ?array
    {
        // iOS: [12/04/2023, 14:32:10] resto
        $ios = '/^\[(\d{1,2}\/\d{1,2}\/\d{2,4}),?\s+(\d{1,2}:\d{2}(?::\d{2})?(?:\s?[ap]\.?\s?m\.?)?)\]\s*(.*)$/iu';
        if (preg_match($ios, $line, $m)) {
            return [$m[1], $m[2], $m[3]];
        }

        // Android: 12/04/2023, 14:32 - resto
        $android = '/^(\d{1,2}\/\d{1,2}\/\d{2,4}),?\s+(\d{1,2}:\d{2}(?::\d{2})?(?:\s?[ap]\.?\s?m\.?)?)\s+-\s+(.*)$/iu';
        if (preg_match($android, $line, $m)) {
            return [$m[1], $m[2], $m[3]];
        }

        return null;
    }

    /**
     * Separa "Nombre: cuerpo". Devuelve null si parece mensaje de sistema
     * (sin nombre de remitente reconocible).
     *
     * @return array{0:string,1:string}|null
     */
    private function splitSender(string $remainder): ?array
    {
        $pos = mb_strpos($remainder, ': ');
        // Caso "Nombre:" al final sin cuerpo (raro pero posible).
        if ($pos === false && str_ends_with($remainder, ':')) {
            $pos = mb_strlen($remainder) - 1;
        }

        if ($pos === false) {
            return null; // sin "Nombre:" → mensaje de sistema
        }

        $sender = trim(mb_substr($remainder, 0, $pos));
        $body = ltrim(mb_substr($remainder, $pos + 2));

        // Un "nombre" demasiado largo o con salto de línea casi seguro es texto
        // de sistema (ej. avisos de cifrado), no un remitente real.
        if ($sender === '' || mb_strlen($sender) > 80 || str_contains($sender, "\n")) {
            return null;
        }

        return [$sender, $body];
    }

    private function parseTimestamp(string $dateStr, string $timeStr, bool $dayFirst): ?CarbonInterface
    {
        $parts = preg_split('/[\/\.\-]/', $dateStr);
        if (count($parts) !== 3) {
            return null;
        }

        [$a, $b, $year] = $parts;
        $year = (int) $year;
        if ($year < 100) {
            $year += 2000;
        }

        $first = (int) $a;
        $second = (int) $b;

        // Si un componente es > 12, desambigua sin importar la preferencia.
        if ($first > 12 && $second <= 12) {
            $day = $first;
            $month = $second;
        } elseif ($second > 12 && $first <= 12) {
            $day = $second;
            $month = $first;
        } else {
            $day = $dayFirst ? $first : $second;
            $month = $dayFirst ? $second : $first;
        }

        if ($month < 1 || $month > 12 || $day < 1 || $day > 31) {
            return null;
        }

        // Normaliza hora (admite "2:05:30 p.m." / "14:05").
        $time = $this->parseTime($timeStr);
        if ($time === null) {
            return null;
        }

        try {
            return Carbon::create($year, $month, $day, $time[0], $time[1], $time[2]);
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return array{0:int,1:int,2:int}|null  [hora, minuto, segundo] */
    private function parseTime(string $timeStr): ?array
    {
        $timeStr = strtolower(trim($timeStr));
        $pm = false;
        $am = false;

        if (preg_match('/([ap])\.?\s?m\.?/', $timeStr, $mm)) {
            $pm = $mm[1] === 'p';
            $am = $mm[1] === 'a';
            $timeStr = trim(preg_replace('/[ap]\.?\s?m\.?/', '', $timeStr));
        }

        $bits = explode(':', $timeStr);
        if (count($bits) < 2) {
            return null;
        }

        $hour = (int) $bits[0];
        $min = (int) $bits[1];
        $sec = isset($bits[2]) ? (int) $bits[2] : 0;

        if ($pm && $hour < 12) {
            $hour += 12;
        }
        if ($am && $hour === 12) {
            $hour = 0;
        }

        if ($hour > 23 || $min > 59 || $sec > 59) {
            return null;
        }

        return [$hour, $min, $sec];
    }

    /**
     * Cierra un mensaje: detecta multimedia adjunta / omitida en el cuerpo.
     *
     * @param  array{ts:?CarbonInterface,sender:string,body:string}  $current
     * @return array{ts:?CarbonInterface,sender:string,body:string,media_file:?string,is_media_omitted:bool}
     */
    private function finalizeMessage(array $current): array
    {
        $body = $current['body'];
        $mediaFile = null;
        $omitted = false;

        $trimmed = trim($body);

        // iOS: "<attached: NOMBRE>"  /  ES: "<adjunto: NOMBRE>"
        if (preg_match('/<(?:attached|adjunto):\s*([^>]+)>/iu', $trimmed, $m)) {
            $mediaFile = trim($m[1]);
            $body = '';
        }
        // Android: "NOMBRE (file attached)" / "NOMBRE (archivo adjunto)"
        elseif (preg_match('/^(.+?)\s*\((?:file attached|archivo adjunto)\)\s*$/iu', $trimmed, $m)) {
            $mediaFile = trim($m[1]);
            $body = '';
        }
        // Multimedia exportada sin archivos.
        elseif (preg_match('/^(?:<Media omitted>|<Multimedia omitido>|‎?image omitted|imagen omitida|audio omitido|video omitido|sticker omitido|GIF omitido|documento omitido)$/iu', $trimmed)) {
            $omitted = true;
        }

        return [
            'ts' => $current['ts'],
            'sender' => $current['sender'],
            'body' => $body,
            'media_file' => $mediaFile,
            'is_media_omitted' => $omitted,
        ];
    }
}
