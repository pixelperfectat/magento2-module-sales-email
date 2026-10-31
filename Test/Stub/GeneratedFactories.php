<?php declare(strict_types=1);

/**
 * Stand-ins for factories that Magento generates at runtime, so unit tests run without generated/.
 * Each is declared only when the real (generated) class is not available.
 */

namespace Magento\Cms\Model\ResourceModel\Block {
    if (!class_exists(CollectionFactory::class)) {
        class CollectionFactory
        {
            /**
             * @param array<string, mixed> $data
             * @return Collection
             */
            public function create(array $data = []): Collection
            {
                throw new \LogicException('Stub: mock this method.');
            }
        }
    }
}

namespace PixelPerfect\SalesEmail\Api\Data {
    if (!class_exists(SanitizedHtmlInterfaceFactory::class)) {
        class SanitizedHtmlInterfaceFactory
        {
            /**
             * @param array{html?: string, removed?: string[]} $data
             * @return SanitizedHtmlInterface
             */
            public function create(array $data = []): SanitizedHtmlInterface
            {
                return new \PixelPerfect\SalesEmail\Model\Data\SanitizedHtml($data['html'] ?? '', $data['removed'] ?? []);
            }
        }
    }
}

namespace PixelPerfect\SalesEmail\Api\Data {
    if (!class_exists(TrackingInterfaceFactory::class)) {
        class TrackingInterfaceFactory
        {
            /**
             * @param array{number?: string, carrierTitle?: string, url?: string|null} $data
             * @return TrackingInterface
             */
            public function create(array $data = []): TrackingInterface
            {
                return new \PixelPerfect\SalesEmail\Model\Data\Tracking(
                    $data['number'] ?? '',
                    $data['carrierTitle'] ?? '',
                    $data['url'] ?? null
                );
            }
        }
    }
}
