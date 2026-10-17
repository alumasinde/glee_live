<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Core\QrCode;
use PHPUnit\Framework\TestCase;

final class QrCodeTest extends TestCase
{
    public function testSvgContainsVectorMarkup(): void
    {
        $svg = QrCode::svg('https://example.test/pass/abc');

        $this->assertStringContainsString('<svg', $svg);
        $this->assertStringContainsString('xmlns', $svg);
    }

    public function testDataUriRoundTripsToSvg(): void
    {
        $svg = QrCode::svg('TOKEN123');
        $uri = QrCode::svgDataUri('TOKEN123');

        $this->assertStringStartsWith('data:image/svg+xml;base64,', $uri);
        $this->assertSame($svg, base64_decode(substr($uri, strlen('data:image/svg+xml;base64,')), true));
    }

    public function testSizeIsHonoured(): void
    {
        $this->assertStringContainsString('width="120"', QrCode::svg('x', 120));
    }
}
