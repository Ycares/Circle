<?php

declare(strict_types=1);

namespace App\Tests\Application\UseCase\AddBookToLibrary;

use App\Application\UseCase\AddBookToLibrary\AddBookToLibraryUseCase;
use App\Domain\Entity\Book;
use App\Domain\Entity\User;
use App\Domain\Entity\UserBook;
use App\Domain\Exception\BookAlreadyInLibraryException;
use App\Domain\Exception\EntityNotFoundException;
use App\Domain\Repository\BookRepositoryInterface;
use App\Domain\Repository\UserBookRepositoryInterface;
use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\ValueObject\BookStatus;
use App\Domain\ValueObject\EmailAddress;
use App\Tests\Support\SetsEntityId;
use PHPUnit\Framework\TestCase;

final class AddBookToLibraryUseCaseTest extends TestCase
{
    use SetsEntityId;

    private User $user;

    protected function setUp(): void
    {
        $this->user = new User(new EmailAddress('user@example.com'), 'hash', 'Pseudo', 'fr', new \DateTimeImmutable());
        $this->setEntityId($this->user, 1);
    }

    public function testCreatesNewBookWhenExternalIdUnknown(): void
    {
        $userRepository = $this->createStub(UserRepositoryInterface::class);
        $bookRepository = $this->createMock(BookRepositoryInterface::class);
        $userBookRepository = $this->createMock(UserBookRepositoryInterface::class);

        $userRepository->method('ofId')->willReturn($this->user);
        $bookRepository->expects(self::once())->method('ofExternalId')->with('gb-1')->willReturn(null);
        $bookRepository->expects(self::once())->method('save');
        $userBookRepository->method('ofUserAndBook')->willReturn(null);
        $userBookRepository->expects(self::once())->method('save');

        $useCase = new AddBookToLibraryUseCase($userRepository, $bookRepository, $userBookRepository);
        $useCase->execute(1, 'gb-1', 'Le Titre', ['Autrice'], 'fr', null, ['Roman']);
    }

    public function testReusesExistingBookByExternalId(): void
    {
        $userRepository = $this->createStub(UserRepositoryInterface::class);
        $bookRepository = $this->createMock(BookRepositoryInterface::class);
        $userBookRepository = $this->createMock(UserBookRepositoryInterface::class);

        $existingBook = new Book('Le Titre', ['Autrice'], 'fr', null, 'gb-1', ['Roman'], new \DateTimeImmutable());

        $userRepository->method('ofId')->willReturn($this->user);
        $bookRepository->expects(self::once())->method('ofExternalId')->with('gb-1')->willReturn($existingBook);
        $bookRepository->expects(self::never())->method('save');
        $userBookRepository->method('ofUserAndBook')->willReturn(null);
        $userBookRepository->expects(self::once())->method('save');

        $useCase = new AddBookToLibraryUseCase($userRepository, $bookRepository, $userBookRepository);
        $useCase->execute(1, 'gb-1', 'Le Titre', ['Autrice'], 'fr', null, ['Roman']);
    }

    public function testThrowsWhenUserNotFound(): void
    {
        $userRepository = $this->createStub(UserRepositoryInterface::class);
        $bookRepository = $this->createStub(BookRepositoryInterface::class);
        $userBookRepository = $this->createStub(UserBookRepositoryInterface::class);

        $userRepository->method('ofId')->willReturn(null);

        $this->expectException(EntityNotFoundException::class);

        $useCase = new AddBookToLibraryUseCase($userRepository, $bookRepository, $userBookRepository);
        $useCase->execute(404, 'gb-1', 'Le Titre', ['Autrice'], 'fr', null, ['Roman']);
    }

    public function testThrowsWhenBookAlreadyInLibrary(): void
    {
        $userRepository = $this->createStub(UserRepositoryInterface::class);
        $bookRepository = $this->createStub(BookRepositoryInterface::class);
        $userBookRepository = $this->createMock(UserBookRepositoryInterface::class);

        $book = new Book('Le Titre', ['Autrice'], 'fr', null, 'gb-1', ['Roman'], new \DateTimeImmutable());
        $existingUserBook = new UserBook($this->user, $book, BookStatus::TO_READ, null, 0, new \DateTimeImmutable(), new \DateTimeImmutable());

        $userRepository->method('ofId')->willReturn($this->user);
        $bookRepository->method('ofExternalId')->willReturn($book);
        $userBookRepository->method('ofUserAndBook')->willReturn($existingUserBook);
        $userBookRepository->expects(self::never())->method('save');

        $this->expectException(BookAlreadyInLibraryException::class);

        $useCase = new AddBookToLibraryUseCase($userRepository, $bookRepository, $userBookRepository);
        $useCase->execute(1, 'gb-1', 'Le Titre', ['Autrice'], 'fr', null, ['Roman']);
    }
}
