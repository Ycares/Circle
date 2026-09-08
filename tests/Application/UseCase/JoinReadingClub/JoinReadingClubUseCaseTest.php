<?php

declare(strict_types=1);

namespace App\Tests\Application\UseCase\JoinReadingClub;

use App\Application\UseCase\JoinReadingClub\JoinReadingClubUseCase;
use App\Domain\Entity\ClubMembership;
use App\Domain\Entity\ReadingClub;
use App\Domain\Entity\User;
use App\Domain\Exception\AlreadyClubMemberException;
use App\Domain\Exception\ClubNotJoinableException;
use App\Domain\Repository\ClubMembershipRepositoryInterface;
use App\Domain\Repository\ReadingClubRepositoryInterface;
use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\ValueObject\ClubRole;
use App\Domain\ValueObject\ClubVisibility;
use App\Domain\ValueObject\EmailAddress;
use App\Tests\Support\SetsEntityId;
use PHPUnit\Framework\TestCase;

final class JoinReadingClubUseCaseTest extends TestCase
{
    use SetsEntityId;

    private User $user;
    private User $host;

    protected function setUp(): void
    {
        $this->user = new User(new EmailAddress('user@example.com'), 'hash', 'User', 'fr', new \DateTimeImmutable());
        $this->setEntityId($this->user, 1);

        $this->host = new User(new EmailAddress('host@example.com'), 'hash', 'Host', 'fr', new \DateTimeImmutable());
        $this->setEntityId($this->host, 2);
    }

    public function testJoinsPublicClubDirectly(): void
    {
        $userRepository = $this->createStub(UserRepositoryInterface::class);
        $readingClubRepository = $this->createStub(ReadingClubRepositoryInterface::class);
        $clubMembershipRepository = $this->createMock(ClubMembershipRepositoryInterface::class);

        $club = new ReadingClub('Club', 'Description', ClubVisibility::PUBLIC, $this->host, new \DateTimeImmutable());

        $userRepository->method('ofId')->willReturn($this->user);
        $readingClubRepository->method('ofId')->willReturn($club);
        $clubMembershipRepository->method('ofUserAndClub')->willReturn(null);

        $clubMembershipRepository->expects(self::once())
            ->method('save')
            ->with(self::callback(fn (ClubMembership $membership): bool => ClubRole::MEMBER === $membership->role()));

        $useCase = new JoinReadingClubUseCase($userRepository, $readingClubRepository, $clubMembershipRepository);
        $useCase->execute(1, 10);
    }

    public function testThrowsWhenAlreadyMember(): void
    {
        $userRepository = $this->createStub(UserRepositoryInterface::class);
        $readingClubRepository = $this->createStub(ReadingClubRepositoryInterface::class);
        $clubMembershipRepository = $this->createMock(ClubMembershipRepositoryInterface::class);

        $club = new ReadingClub('Club', 'Description', ClubVisibility::PUBLIC, $this->host, new \DateTimeImmutable());
        $existingMembership = new ClubMembership($this->user, $club, ClubRole::MEMBER, new \DateTimeImmutable());

        $userRepository->method('ofId')->willReturn($this->user);
        $readingClubRepository->method('ofId')->willReturn($club);
        $clubMembershipRepository->method('ofUserAndClub')->willReturn($existingMembership);
        $clubMembershipRepository->expects(self::never())->method('save');

        $this->expectException(AlreadyClubMemberException::class);

        $useCase = new JoinReadingClubUseCase($userRepository, $readingClubRepository, $clubMembershipRepository);
        $useCase->execute(1, 10);
    }

    public function testThrowsWhenClubIsPrivate(): void
    {
        $userRepository = $this->createStub(UserRepositoryInterface::class);
        $readingClubRepository = $this->createStub(ReadingClubRepositoryInterface::class);
        $clubMembershipRepository = $this->createMock(ClubMembershipRepositoryInterface::class);

        $club = new ReadingClub('Club', 'Description', ClubVisibility::PRIVATE_INVITE, $this->host, new \DateTimeImmutable());

        $userRepository->method('ofId')->willReturn($this->user);
        $readingClubRepository->method('ofId')->willReturn($club);
        $clubMembershipRepository->method('ofUserAndClub')->willReturn(null);
        $clubMembershipRepository->expects(self::never())->method('save');

        $this->expectException(ClubNotJoinableException::class);

        $useCase = new JoinReadingClubUseCase($userRepository, $readingClubRepository, $clubMembershipRepository);
        $useCase->execute(1, 10);
    }
}
