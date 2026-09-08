<?php

declare(strict_types=1);

namespace App\Tests\Application\UseCase\DeclareClubProgress;

use App\Application\UseCase\DeclareClubProgress\DeclareClubProgressUseCase;
use App\Domain\Entity\ClubMembership;
use App\Domain\Entity\ReadingClub;
use App\Domain\Entity\User;
use App\Domain\Exception\EntityNotFoundException;
use App\Domain\Repository\ClubMembershipRepositoryInterface;
use App\Domain\Repository\ReadingClubRepositoryInterface;
use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\ValueObject\ClubRole;
use App\Domain\ValueObject\ClubVisibility;
use App\Domain\ValueObject\EmailAddress;
use PHPUnit\Framework\TestCase;

final class DeclareClubProgressUseCaseTest extends TestCase
{
    public function testDeclaresChapterWithinBounds(): void
    {
        $userRepository = $this->createStub(UserRepositoryInterface::class);
        $readingClubRepository = $this->createStub(ReadingClubRepositoryInterface::class);
        $clubMembershipRepository = $this->createMock(ClubMembershipRepositoryInterface::class);

        $host = new User(new EmailAddress('host@example.com'), 'hash', 'Host', 'fr', new \DateTimeImmutable());
        $club = new ReadingClub('Club', 'Description', ClubVisibility::PUBLIC, $host, new \DateTimeImmutable());
        $club->setChapterCount(5);
        $membership = new ClubMembership($host, $club, ClubRole::MEMBER, new \DateTimeImmutable());

        $userRepository->method('ofId')->willReturn($host);
        $readingClubRepository->method('ofId')->willReturn($club);
        $clubMembershipRepository->method('ofUserAndClub')->willReturn($membership);
        $clubMembershipRepository->expects(self::once())->method('save')->with($membership);

        $useCase = new DeclareClubProgressUseCase($userRepository, $readingClubRepository, $clubMembershipRepository);
        $useCase->execute(1, 10, 3);

        self::assertSame(3, $membership->declaredChapter());
    }

    public function testThrowsWhenDeclaredChapterExceedsChapterCount(): void
    {
        $userRepository = $this->createStub(UserRepositoryInterface::class);
        $readingClubRepository = $this->createStub(ReadingClubRepositoryInterface::class);
        $clubMembershipRepository = $this->createMock(ClubMembershipRepositoryInterface::class);

        $host = new User(new EmailAddress('host@example.com'), 'hash', 'Host', 'fr', new \DateTimeImmutable());
        $club = new ReadingClub('Club', 'Description', ClubVisibility::PUBLIC, $host, new \DateTimeImmutable());
        $club->setChapterCount(5);
        $membership = new ClubMembership($host, $club, ClubRole::MEMBER, new \DateTimeImmutable());

        $userRepository->method('ofId')->willReturn($host);
        $readingClubRepository->method('ofId')->willReturn($club);
        $clubMembershipRepository->method('ofUserAndClub')->willReturn($membership);
        $clubMembershipRepository->expects(self::never())->method('save');

        $this->expectException(\InvalidArgumentException::class);

        $useCase = new DeclareClubProgressUseCase($userRepository, $readingClubRepository, $clubMembershipRepository);
        $useCase->execute(1, 10, 6);
    }

    public function testThrowsWhenNotAMember(): void
    {
        $userRepository = $this->createStub(UserRepositoryInterface::class);
        $readingClubRepository = $this->createStub(ReadingClubRepositoryInterface::class);
        $clubMembershipRepository = $this->createStub(ClubMembershipRepositoryInterface::class);

        $host = new User(new EmailAddress('host@example.com'), 'hash', 'Host', 'fr', new \DateTimeImmutable());
        $club = new ReadingClub('Club', 'Description', ClubVisibility::PUBLIC, $host, new \DateTimeImmutable());

        $userRepository->method('ofId')->willReturn($host);
        $readingClubRepository->method('ofId')->willReturn($club);
        $clubMembershipRepository->method('ofUserAndClub')->willReturn(null);

        $this->expectException(EntityNotFoundException::class);

        $useCase = new DeclareClubProgressUseCase($userRepository, $readingClubRepository, $clubMembershipRepository);
        $useCase->execute(1, 10, 1);
    }
}
