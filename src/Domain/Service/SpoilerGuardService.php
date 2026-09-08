<?php

declare(strict_types=1);

namespace App\Domain\Service;

use App\Domain\Entity\ClubMembership;
use App\Domain\Entity\Discussion;

final class SpoilerGuardService
{
    public function isDiscussionVisibleFor(Discussion $discussion, ClubMembership $membership): bool
    {
        if (null === $discussion->chapter()) {
            return true; // channel global du club, non lié à un chapitre
        }

        return $membership->declaredChapter() >= $discussion->chapter()->chapterNumber();
    }
}
