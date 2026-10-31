<?php

declare(strict_types=1);

namespace PixelPerfect\SalesEmail\Api\Service;

interface StoreEmulatorInterface
{
    /**
     * Run a callback in the frontend environment of a store
     *
     * Emulates only when the current store or design area differs, so a render that core already emulates
     * (email sending, also from admin, cron or queue) is never started a second time and never stopped here.
     *
     * @param int $storeId
     * @param callable $callback
     * @return mixed the callback result
     */
    public function emulate(int $storeId, callable $callback): mixed;
}
