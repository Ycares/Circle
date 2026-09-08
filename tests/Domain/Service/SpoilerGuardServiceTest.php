<?php

declare(strict_types=1);

namespace App\Tests\Domain\Service;

use App\Domain\Entity\Chapter;
use App\Domain\Entity\ClubMembership;
use App\Domain\Entity\Discussion;
use App\Domain\Entity\ReadingClub;
use App\Domain\Entity\User;
use App\Domain\Service\SpoilerGuardService;
use App\Domain\ValueObject\ClubRole;
use App\Domain\ValueObject\ClubVisibility;
use App\Domain\ValueObject\EmailAddress;
use PHPUnit\Framework\TestCase;

final class SpoilerGuardServiceTest extends TestCase
{
    private SpoilerGuardService $spoilerGuardService;
    private ReadingClub $readingClub;
    private User $member;

    protected function setUp(): void
    {
        $this->spoilerGuardService = new SpoilerGuardService();

        $host = new User(
            new EmailAddress('host@example.com'),
            'hashed-password',
            'Host',
            'fr',
            new \DateTimeImmutable(),
        );

        $this->readingClub = new ReadingClub(
            'Club de lecture',
            'Description',
            ClubVisibility::PUBLIC,
            $host,
            new \DateTimeImmutable(),
        );

        $this->member = new User(
            new EmailAddress('member@example.com'),
            'hashed-password',
            'Member',
            'fr',
            new \DateTimeImmutable(),
        );
    }

    public function testDiscussionWithoutChapterIsAlwaysVisible(): void
    {
        $discussion = new Discussion($this->readingClub, null, $this->member, 'Bienvenue au club', new \DateTimeImmutable());
        $membership = $this->membershipWithDeclaredChapter(0);

        self::assertTrue($this->spoilerGuardService->isDiscussionVisibleFor($discussion, $membership));
    }

    public function testDiscussionIsVisibleWhenDeclaredChapterEqualsChapterNumber(): void
    {
        $chapter = new Chapter($this->readingClub, 3);
        $discussion = new Discussion($this->readingClub, $chapter, $this->member, 'Discussion du chapitre 3', new \DateTimeImmutable());
        $membership = $this->membershipWithDeclaredChapter(3);

        self::assertTrue($this->spoilerGuardService->isDiscussionVisibleFor($discussion, $membership));
    }

    public function testDiscussionIsNotVisibleWhenDeclaredChapterIsLowerThanChapterNumber(): void
    {
        $chapter = new Chapter($this->readingClub, 3);
        $discussion = new Discussion($this->readingClub, $chapter, $this->member, 'Discussion du chapitre 3', new \DateTimeImmutable());
        $membership = $this->membershipWithDeclaredChapter(2);

        self::assertFalse($this->spoilerGuardService->isDiscussionVisibleFor($discussion, $membership));
    }

    public function testDiscussionIsVisibleWhenDeclaredChapterIsHigherThanChapterNumber(): void
    {
        $chapter = new Chapter($this->readingClub, 3);
        $discussion = new Discussion($this->readingClub, $chapter, $this->member, 'Discussion du chapitre 3', new \DateTimeImmutable());
        $membership = $this->membershipWithDeclaredChapter(5);

        self::assertTrue($this->spoilerGuardService->isDiscussionVisibleFor($discussion, $membership));
    }

    private function membershipWithDeclaredChapter(int $declaredChapter): ClubMembership
    {
        $membership = new ClubMembership($this->member, $this->readingClub, ClubRole::MEMBER, new \DateTimeImmutable());
        $membership->declareChapter($declaredChapter);

        return $membership;
    }
}
