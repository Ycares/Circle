<?php

declare(strict_types=1);

namespace App\Tests\Application\UseCase\CreateReadingClub;

use App\Application\UseCase\CreateReadingClub\CreateReadingClubUseCase;
use App\Domain\Entity\ClubMembership;
use App\Domain\Entity\ReadingClub;
use App\Domain\Entity\User;
use App\Domain\Exception\EntityNotFoundException;
use App\Domain\Repository\ClubMembershipRepositoryInterface;
use App\Domain\Repository\ReadingClubRepositoryInterface;
use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\ValueObject\ClubRole;
use App\Domain\ValueObject\EmailAddress;
use App\Tests\Support\SetsEntityId;
use PHPUnit\Framework\TestCase;

final class CreateReadingClubUseCaseTest extends TestCase
{
    use SetsEntityId;

    public function testCreatesClubAndMakesHostAMember(): void
    {
        $userRepository = $this->createStub(UserRepositoryInterface::class);
        $readingClubRepository = $this->createMock(ReadingClubRepositoryInterface::class);
        $clubMembershipRepository = $this->createMock(ClubMembershipRepositoryInterface::class);

        $host = new User(new EmailAddress('host@example.com'), 'hash', 'Host', 'fr', new \DateTimeImmutable());
        $this->setEntityId($host, 1);

        $userRepository->method('ofId')->willReturn($host);

        $savedClub = null;
        $readingClubRepository->expects(self::once())
            ->method('save')
            ->with(self::callback(function (ReadingClub $club) use (&$savedClub): bool {
                $savedClub = $club;

                return true;
            }));

        $clubMembershipRepository->expects(self::once())
            ->method('save')
            ->with(self::callback(fn (ClubMembership $membership): bool => ClubRole::HOST === $membership->role()));

        $useCase = new CreateReadingClubUseCase($userRepository, $readingClubRepository, $clubMembershipRepository);
        $useCase->execute(1, 'Club de SF', 'Un club sympa', 'public');

        self::assertInstanceOf(ReadingClub::class, $savedClub);
        self::assertSame('Club de SF', $savedClub->name());
    }

    public function testThrowsWhenHostNotFound(): void
    {
        $userRepository = $this->createStub(UserRepositoryInterface::class);
        $readingClubRepository = $this->createStub(ReadingClubRepositoryInterface::class);
        $clubMembershipRepository = $this->createStub(ClubMembershipRepositoryInterface::class);

        $userRepository->method('ofId')->willReturn(null);

        $this->expectException(EntityNotFoundException::class);

        $useCase = new CreateReadingClubUseCase($userRepository, $readingClubRepository, $clubMembershipRepository);
        $useCase->execute(404, 'Club', 'Description', 'public');
    }
}
