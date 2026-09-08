<?php

declare(strict_types=1);

namespace App\Application\UseCase\ReviewBook;

use App\Domain\Entity\Review;
use App\Domain\Exception\EntityNotFoundException;
use App\Domain\Exception\UnauthorizedActionException;
use App\Domain\Repository\ReviewRepositoryInterface;
use App\Domain\Repository\UserBookRepositoryInterface;

final class ReviewBookUseCase
{
    public function __construct(
        private readonly UserBookRepositoryInterface $userBookRepository,
        private readonly ReviewRepositoryInterface $reviewRepository,
    ) {
    }

    public function execute(int $userBookId, int $userId, string $text, bool $containsSpoiler): void
    {
        $userBook = $this->userBookRepository->ofId($userBookId);

        if (null === $userBook) {
            throw new EntityNotFoundException('UserBook', $userBookId);
        }

        if ($userBook->user()->id() !== $userId) {
            throw new UnauthorizedActionException('Cette bibliothèque ne vous appartient pas.');
        }

        $this->reviewRepository->save(new Review($userBook, $text, $containsSpoiler, new \DateTimeImmutable()));
    }
}
