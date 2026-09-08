<?php

declare(strict_types=1);

namespace App\Application\UseCase\AddBookToLibrary;

use App\Domain\Entity\Book;
use App\Domain\Entity\UserBook;
use App\Domain\Exception\BookAlreadyInLibraryException;
use App\Domain\Exception\EntityNotFoundException;
use App\Domain\Repository\BookRepositoryInterface;
use App\Domain\Repository\UserBookRepositoryInterface;
use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\ValueObject\BookStatus;

final class AddBookToLibraryUseCase
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly BookRepositoryInterface $bookRepository,
        private readonly UserBookRepositoryInterface $userBookRepository,
    ) {
    }

    /**
     * @param list<string> $authors
     * @param list<string> $genres
     */
    public function execute(
        int $userId,
        string $externalId,
        string $title,
        array $authors,
        string $language,
        ?string $coverUrl,
        array $genres,
    ): void {
        $user = $this->userRepository->ofId($userId);

        if (null === $user) {
            throw new EntityNotFoundException('User', $userId);
        }

        $book = $this->bookRepository->ofExternalId($externalId);

        if (null === $book) {
            $book = new Book($title, $authors, $language, $coverUrl, $externalId, $genres, new \DateTimeImmutable());
            $this->bookRepository->save($book);
        }

        if (null !== $this->userBookRepository->ofUserAndBook($user, $book)) {
            throw new BookAlreadyInLibraryException();
        }

        $this->userBookRepository->save(new UserBook(
            $user,
            $book,
            BookStatus::TO_READ,
            null,
            0,
            new \DateTimeImmutable(),
            new \DateTimeImmutable(),
        ));
    }
}
