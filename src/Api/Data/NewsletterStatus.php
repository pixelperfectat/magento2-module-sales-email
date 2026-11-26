<?php declare(strict_types=1);

namespace PixelPerfect\SalesEmail\Api\Data;

/**
 * Newsletter status of an email recipient, as far as the invitation block needs it
 *
 * Unknown: no newsletter sign-up module installed, no email, or the status could not be read. The invitation
 * then shows as without any status.
 */
enum NewsletterStatus
{
    case Unknown;
    case NotSubscribed;
    case Pending;
    case Confirmed;
    case Unsubscribed;
}
