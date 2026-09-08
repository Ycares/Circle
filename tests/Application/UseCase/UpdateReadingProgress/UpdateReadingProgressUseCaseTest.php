<?php

declare(strict_types=1);

namespace App\Tests\Application\UseCase\UpdateReadingProgress;

use App\Application\UseCase\UpdateReadingProgress\UpdateReadingProgressUseCase;
use App\Domain\Entity\Book;
use App\Domain\Entity\User;
use App\Domain\Entity\UserBook;
use App\Domain\Exception\EntityNotFoundException;
use App\Domain\Exception\UnauthorizedActionException;
use App\Domain\Repository\UserBookRepositoryInterface;
use App\Domain\ValueObject\BookStatus;
use App\Domain\ValueObject\EmailAddress;
use App\Tests\Support\SetsEntityId;
use PHPUnit\Framework\TestCase;

final class UpdateReadingProgressUseCaseTest extends TestCase
{
    use SetsEntityId;

    private function createUserBook(int $ownerId): UserBook
    {
        $owner = new User(new EmailAddress('owner@example.com'), 'hash', 'Owner', 'fr', new \DateTimeImmutable());
        $this->setEntityId($owner, $ownerId);

        $book = new Book('Titre', ['Auteur'], 'fr', null, 'gb-1', ['Roman'], new \DateTimeImmutable());

        return new UserBook($owner, $book, BookStatus::READING, null, 10, new \DateTimeImmutable(), new \DateTimeImmutable());
    }

    public function testUpdatesProgressWhenOwnedByRequestingUser(): void
    {
        $userBookRepository = $this->createMock(UserBookRepositoryInterface::class);
        $userBook = $this->createUserBook(1);

        $userBookRepository->expects(self::once())->method('ofId')->with(42)->willReturn($userBook);
        $userBookRepository->expects(self::once())->method('save')->with($userBook);

        $useCase = new UpdateReadingProgressUseCase($userBookRepository);
        $useCase->execute(42, 1, 75);

        self::assertSame(75, $userBook->progress());
    }

    public function testThrowsWhenUserBookNotFound(): void
    {
        $userBookRepository = $this->createStub(UserBookRepositoryInterface::class);
        $userBookRepository->method('ofId')->willReturn(null);

        $this->expectException(EntityNotFoundException::class);

        $useCase = new UpdateReadingProgressUseCase($userBookRepository);
        $useCase->execute(404, 1, 75);
    }

    public function testThrowsWhenRequestingUserIsNotOwner(): void
    {
        $userBookRepository = $this->createMock(UserBookRepositoryInterface::class);
        $userBook = $this->createUserBook(1);

        $userBookRepository->method('ofId')->willReturn($userBook);
        $userBookRepository->expects(self::never())->method('save');

        $this->expectException(UnauthorizedActionException::class);

        $useCase = new UpdateReadingProgressUseCase($userBookRepository);
        $useCase->execute(42, 2, 75);
    }
}
