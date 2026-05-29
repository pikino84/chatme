<?php

namespace Tests\Unit;

use App\Services\WhatsAppChatImportService;
use PHPUnit\Framework\TestCase;

class WhatsAppChatImportServiceTest extends TestCase
{
    private WhatsAppChatImportService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = new WhatsAppChatImportService();
    }

    public function test_parses_ios_format(): void
    {
        $txt = "[12/04/2023, 14:32:10] Juan Pérez: Hola, ¿cómo estás?\n"
            . "[12/04/2023, 14:33:01] Yo: Bien, gracias";

        $messages = $this->svc->parse($txt, dayFirst: true);

        $this->assertCount(2, $messages);
        $this->assertSame('Juan Pérez', $messages[0]['sender']);
        $this->assertSame('Hola, ¿cómo estás?', $messages[0]['body']);
        $this->assertSame('2023-04-12 14:32:10', $messages[0]['ts']->format('Y-m-d H:i:s'));
        $this->assertSame('Yo', $messages[1]['sender']);
    }

    public function test_parses_android_format(): void
    {
        $txt = "12/04/2023, 14:32 - Juan Pérez: Hola\n"
            . "12/04/2023, 14:33 - Yo: Qué tal";

        $messages = $this->svc->parse($txt, dayFirst: true);

        $this->assertCount(2, $messages);
        $this->assertSame('Juan Pérez', $messages[0]['sender']);
        $this->assertSame('2023-04-12 14:32:00', $messages[0]['ts']->format('Y-m-d H:i:s'));
    }

    public function test_handles_multiline_messages(): void
    {
        $txt = "[12/04/2023, 14:32:10] Juan: Primera línea\n"
            . "segunda línea\n"
            . "tercera línea\n"
            . "[12/04/2023, 14:35:00] Yo: Respuesta";

        $messages = $this->svc->parse($txt);

        $this->assertCount(2, $messages);
        $this->assertSame("Primera línea\nsegunda línea\ntercera línea", $messages[0]['body']);
    }

    public function test_detects_ios_attached_media(): void
    {
        $txt = "[12/04/2023, 14:32:10] Juan: \u{200e}<attached: IMG-20230412-WA0001.jpg>";

        $messages = $this->svc->parse($txt);

        $this->assertSame('IMG-20230412-WA0001.jpg', $messages[0]['media_file']);
        $this->assertSame('', $messages[0]['body']);
    }

    public function test_detects_android_attached_media(): void
    {
        $txt = "12/04/2023, 14:32 - Juan: IMG-20230412-WA0001.jpg (archivo adjunto)";

        $messages = $this->svc->parse($txt);

        $this->assertSame('IMG-20230412-WA0001.jpg', $messages[0]['media_file']);
    }

    public function test_detects_media_omitted(): void
    {
        $txt = "12/04/2023, 14:32 - Juan: <Multimedia omitido>";

        $messages = $this->svc->parse($txt);

        $this->assertTrue($messages[0]['is_media_omitted']);
        $this->assertNull($messages[0]['media_file']);
    }

    public function test_skips_system_messages(): void
    {
        $txt = "[12/04/2023, 14:00:00] Los mensajes están cifrados de extremo a extremo.\n"
            . "[12/04/2023, 14:32:10] Juan: Hola real";

        $messages = $this->svc->parse($txt);

        // El aviso de cifrado (sin "Nombre:") se descarta.
        $this->assertCount(1, $messages);
        $this->assertSame('Juan', $messages[0]['sender']);
    }

    public function test_disambiguates_day_when_over_12(): void
    {
        // 25 no puede ser mes → día=25, mes=04 aunque dayFirst sea false.
        $txt = "[25/04/2023, 09:00:00] Juan: Test";

        $messages = $this->svc->parse($txt, dayFirst: false);

        $this->assertSame('2023-04-25 09:00:00', $messages[0]['ts']->format('Y-m-d H:i:s'));
    }

    public function test_parses_12h_am_pm(): void
    {
        $txt = "[12/04/2023, 2:05:30 p. m.] Juan: Tarde\n"
            . "[12/04/2023, 9:15:00 a. m.] Juan: Mañana";

        $messages = $this->svc->parse($txt);

        $this->assertSame('14:05:30', $messages[0]['ts']->format('H:i:s'));
        $this->assertSame('09:15:00', $messages[1]['ts']->format('H:i:s'));
    }

    public function test_summarize_counts_senders_and_media(): void
    {
        $txt = "[12/04/2023, 14:32:10] Juan: Hola\n"
            . "[12/04/2023, 14:33:00] Yo: Hey\n"
            . "[12/04/2023, 14:34:00] Juan: <attached: IMG-1.jpg>";

        $summary = $this->svc->summarize($this->svc->parse($txt));

        $this->assertSame(3, $summary['total']);
        $this->assertSame(2, $summary['senders']['Juan']);
        $this->assertSame(1, $summary['senders']['Yo']);
        $this->assertSame(1, $summary['media_count']);
        $this->assertNotNull($summary['first_ts']);
    }

    public function test_media_type_detection(): void
    {
        $this->assertSame('image', $this->svc->mediaTypeFor('IMG-1.jpg'));
        $this->assertSame('video', $this->svc->mediaTypeFor('VID-1.mp4'));
        $this->assertSame('audio', $this->svc->mediaTypeFor('PTT-1.opus'));
        $this->assertSame('sticker', $this->svc->mediaTypeFor('STK-1.webp'));
        $this->assertSame('document', $this->svc->mediaTypeFor('reporte.pdf'));
    }
}
