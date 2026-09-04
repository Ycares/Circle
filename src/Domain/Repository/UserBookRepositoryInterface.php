<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Entity\Book;
use App\Domain\Entity\User;
use App\Domain\Entity\UserBook;

interface UserBookRepositoryInterface
{
    public function save(UserBook $userBook): void;

    public function ofId(int $id): ?UserBook;

    public function ofUserAndBook(User $user, Book $book): ?UserBook;

    /**
     * @return list<UserBook>
     */
    public function ofUser(User $user): array;
}
