<?php declare(strict_types=1);

namespace PixelPerfect\SalesEmail\Api\Service;

use PixelPerfect\SalesEmail\Api\Data\NewsletterStatus;

interface NewsletterStatusReaderInterface
{
    /**
     * Newsletter status of the email address on the website of the store view; Unknown when it cannot be told
     *
     * @param string $email
     * @param int $storeId
     * @return NewsletterStatus
     */
    public function getStatus(string $email, int $storeId): NewsletterStatus;
}
