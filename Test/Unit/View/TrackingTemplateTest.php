<?php

declare(strict_types=1);

namespace PixelPerfect\SalesEmail\Test\Unit\View;

use Magento\Framework\Escaper;
use Magento\Framework\Phrase;
use Magento\Framework\Translate\InlineInterface;
use Magento\Framework\ZendEscaper;
use PHPUnit\Framework\TestCase;
use PixelPerfect\SalesEmail\Model\Data\Tracking;
use PixelPerfect\SalesEmail\ViewModel\ShipmentTracking;
use Psr\Log\LoggerInterface;

class TrackingTemplateTest extends TestCase
{
    private const VIEW = __DIR__ . '/../../../src/view/frontend';

    /**
     * @param Tracking[] $tracks
     */
    private function render(array $tracks): string
    {
        $viewModel = $this->createMock(ShipmentTracking::class);
        $viewModel->method('getTracks')->willReturn($tracks);
        $block = new class ($viewModel) {
            public function __construct(private readonly ShipmentTracking $viewModel)
            {
            }

            public function getData(string $key): mixed
            {
                return $key === 'view_model' ? $this->viewModel : 7;
            }
        };
        // The real Escaper resolves its collaborators through the ObjectManager; inject plain ones by reflection
        // so the template runs unchanged without a Magento bootstrap.
        $escaper = new Escaper(null, $this->createMock(LoggerInterface::class));
        $inline = $this->createMock(InlineInterface::class);
        $inline->method('isAllowed')->willReturn(false);
        (new \ReflectionProperty(Escaper::class, 'translateInline'))->setValue($escaper, $inline);
        (new \ReflectionProperty(Escaper::class, 'escaper'))->setValue($escaper, new ZendEscaper());

        ob_start();
        (static function () use ($block, $escaper): void {
            include self::VIEW . '/templates/email/shipment/tracking.phtml';
        })();

        return (string)ob_get_clean();
    }

    public function testNoTracksRendersNothing(): void
    {
        $this->assertSame('', trim($this->render([])));
    }

    public function testTrackWithUrlHasNumberCarrierAndButton(): void
    {
        $html = $this->render([new Tracking('EX123456789AT', 'Example Parcel', 'https://shop.example.com/t?a=1&b=2')]);

        $this->assertStringContainsString('EX123456789AT', $html);
        $this->assertStringContainsString('Example Parcel', $html);
        $this->assertStringContainsString('Track shipment', $html);
        $this->assertStringContainsString('href="https://shop.example.com/t?a=1&amp;b=2"', $html);
    }

    public function testTrackWithoutUrlHasNoButton(): void
    {
        $html = $this->render([new Tracking('EX123456789AT', 'Example Parcel', null)]);

        $this->assertStringContainsString('EX123456789AT', $html);
        $this->assertStringNotContainsString('Track shipment', $html);
        $this->assertStringNotContainsString('<a ', $html);
    }

    public function testTrackWithoutCarrierOmitsTheCarrierCell(): void
    {
        $html = $this->render([new Tracking('EX1', '', null)]);

        $this->assertStringNotContainsString('Shipped with', $html);
    }

    public function testTwoTracksGiveTwoBoxes(): void
    {
        $html = $this->render([
            new Tracking('EX1', 'Parcel A', 'https://shop.example.com/a'),
            new Tracking('EX2', 'Parcel B', null),
        ]);

        $this->assertSame(2, substr_count($html, 'class="pp-track"'));
        $this->assertSame(1, substr_count($html, 'Track shipment'));
    }

    public function testNumberAndTitleAreEscaped(): void
    {
        $html = $this->render([new Tracking('<b>1</b>', '<i>x</i>', null)]);

        $this->assertStringNotContainsString('<b>1</b>', $html);
        $this->assertStringContainsString('&lt;b&gt;1&lt;/b&gt;', $html);
        $this->assertStringContainsString('&lt;i&gt;x&lt;/i&gt;', $html);
    }
}
