<?php

declare(strict_types=1);

namespace App\Tests\Unit\LiveAt;

use App\Tests\LiveAt\Support\LiveAtReport;
use PHPUnit\Framework\TestCase;

final class LiveAtReportTest extends TestCase
{
    private string $base;

    protected function setUp(): void
    {
        $this->base = sys_get_temp_dir().'/at-live-test-'.bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->base.'/*/*') ?: [] as $file) {
            unlink($file);
        }
        foreach (glob($this->base.'/*') ?: [] as $dir) {
            rmdir($dir);
        }
        @rmdir($this->base);
    }

    public function testThePasswordMaterialOfTheSecurityHeaderIsBlanked(): void
    {
        $redacted = LiveAtReport::redact('<wss:UsernameToken><wss:Username>508025090/1</wss:Username><wss:Password>SECRETBLOB</wss:Password><wss:Nonce>NN</wss:Nonce><wss:Created>CC</wss:Created></wss:UsernameToken>');

        self::assertStringContainsString('<wss:Username>508025090/1</wss:Username>', $redacted);
        self::assertStringNotContainsString('SECRETBLOB', $redacted);
        self::assertStringContainsString('<wss:Password>***</wss:Password>', $redacted);
        self::assertStringContainsString('<wss:Nonce>***</wss:Nonce>', $redacted);
    }

    public function testEveryExchangeIsFiledWithItsRequestAndResponse(): void
    {
        $report = new LiveAtReport($this->base, 'abc123');

        $report->saveExchange('FT-basic', 'RegisterInvoice', '<a><wss:Password>X</wss:Password></a>', '<ok/>');

        $files = array_map('basename', glob($report->directory().'/*') ?: []);
        sort($files);
        self::assertSame(['01-FT-basic-RegisterInvoice.request.xml', '01-FT-basic-RegisterInvoice.response.xml'], $files);
        self::assertStringNotContainsString('>X<', (string) file_get_contents($report->directory().'/01-FT-basic-RegisterInvoice.request.xml'));
    }
}
