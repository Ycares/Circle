<?php

declare(strict_types=1);

namespace App\Application\UseCase\UpdateReadingProgress;

use App\Domain\Exception\EntityNotFoundException;
use App\Domain\Exception\UnauthorizedActionException;
use App\Domain\Repository\UserBookRepositoryInterface;

final class UpdateReadingProgressUseCase
{
    public function __construct(private readonly UserBookRepositoryInterface $userBookRepository)
    {
    }

    public function execute(int $userBookId, int $userId, int $progress): void
    {
        $userBook = $this->userBookRepository->ofId($userBookId);

        if (null === $userBook) {
            throw new EntityNotFoundException('UserBook', $userBookId);
        }

        if ($userBook->user()->id() !== $userId) {
            throw new UnauthorizedActionException('Cette bibliothèque ne vous appartient pas.');
        }

        $userBook->updateProgress($progress);
        $this->userBookRepository->save($userBook);
    }
}
