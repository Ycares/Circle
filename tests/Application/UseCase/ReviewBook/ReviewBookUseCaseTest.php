<?php

declare(strict_types=1);

namespace App\Tests\Application\UseCase\ReviewBook;

use App\Application\UseCase\ReviewBook\ReviewBookUseCase;
use App\Domain\Entity\Book;
use App\Domain\Entity\Review;
use App\Domain\Entity\User;
use App\Domain\Entity\UserBook;
use App\Domain\Exception\EntityNotFoundException;
use App\Domain\Exception\UnauthorizedActionException;
use App\Domain\Repository\ReviewRepositoryInterface;
use App\Domain\Repository\UserBookRepositoryInterface;
use App\Domain\ValueObject\BookStatus;
use App\Domain\ValueObject\EmailAddress;
use App\Tests\Support\SetsEntityId;
use PHPUnit\Framework\TestCase;

final class ReviewBookUseCaseTest extends TestCase
{
    use SetsEntityId;

    private function createUserBook(int $ownerId): UserBook
    {
        $owner = new User(new EmailAddress('owner@example.com'), 'hash', 'Owner', 'fr', new \DateTimeImmutable());
        $this->setEntityId($owner, $ownerId);

        $book = new Book('Titre', ['Auteur'], 'fr', null, 'gb-1', ['Roman'], new \DateTimeImmutable());

        return new UserBook($owner, $book, BookStatus::READ, null, 100, new \DateTimeImmutable(), new \DateTimeImmutable());
    }

    public function testPublishesReviewForOwnedUserBook(): void
    {
        $userBookRepository = $this->createMock(UserBookRepositoryInterface::class);
        $reviewRepository = $this->createMock(ReviewRepositoryInterface::class);

        $userBook = $this->createUserBook(1);
        $userBookRepository->expects(self::once())->method('ofId')->with(42)->willReturn($userBook);

        $savedReview = null;
        $reviewRepository->expects(self::once())
            ->method('save')
            ->with(self::callback(function (Review $review) use (&$savedReview): bool {
                $savedReview = $review;

                return true;
            }));

        $useCase = new ReviewBookUseCase($userBookRepository, $reviewRepository);
        $useCase->execute(42, 1, 'Excellent livre', true);

        self::assertSame('Excellent livre', $savedReview->text());
        self::assertTrue($savedReview->containsSpoiler());
    }

    public function testThrowsWhenUserBookNotFound(): void
    {
        $userBookRepository = $this->createStub(UserBookRepositoryInterface::class);
        $reviewRepository = $this->createStub(ReviewRepositoryInterface::class);

        $userBookRepository->method('ofId')->willReturn(null);

        $this->expectException(EntityNotFoundException::class);

        $useCase = new ReviewBookUseCase($userBookRepository, $reviewRepository);
        $useCase->execute(404, 1, 'Texte', false);
    }

    public function testThrowsWhenRequestingUserIsNotOwner(): void
    {
        $userBookRepository = $this->createStub(UserBookRepositoryInterface::class);
        $reviewRepository = $this->createMock(ReviewRepositoryInterface::class);

        $userBook = $this->createUserBook(1);
        $userBookRepository->method('ofId')->willReturn($userBook);
        $reviewRepository->expects(self::never())->method('save');

        $this->expectException(UnauthorizedActionException::class);

        $useCase = new ReviewBookUseCase($userBookRepository, $reviewRepository);
        $useCase->execute(42, 2, 'Texte', false);
    }
}
