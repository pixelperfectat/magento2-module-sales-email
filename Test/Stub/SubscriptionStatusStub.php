<?php declare(strict_types=1);

namespace PixelPerfect\SalesEmail\Test\Stub;

/**
 * Stand-in for the status enum of the newsletter sign-up module, which is not installed in this package's tests.
 * The reader maps statuses by case name only, so the stub carries the same case names.
 */
enum SubscriptionStatusStub
{
    case NotSubscribed;
    case Pending;
    case Confirmed;
    case Unsubscribed;
    case SomethingNew;
}
