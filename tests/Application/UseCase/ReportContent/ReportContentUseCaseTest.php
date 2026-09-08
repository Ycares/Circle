<?php

declare(strict_types=1);

namespace App\Tests\Application\UseCase\ReportContent;

use App\Application\UseCase\ReportContent\ReportContentUseCase;
use App\Domain\Entity\Report;
use App\Domain\Entity\User;
use App\Domain\Exception\EntityNotFoundException;
use App\Domain\Repository\ReportRepositoryInterface;
use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\ValueObject\EmailAddress;
use PHPUnit\Framework\TestCase;

final class ReportContentUseCaseTest extends TestCase
{
    private User $reporter;

    protected function setUp(): void
    {
        $this->reporter = new User(new EmailAddress('reporter@example.com'), 'hash', 'Reporter', 'fr', new \DateTimeImmutable());
    }

    public function testReportsADiscussion(): void
    {
        $userRepository = $this->createStub(UserRepositoryInterface::class);
        $reportRepository = $this->createMock(ReportRepositoryInterface::class);

        $userRepository->method('ofId')->willReturn($this->reporter);

        $savedReport = null;
        $reportRepository->expects(self::once())
            ->method('save')
            ->with(self::callback(function (Report $report) use (&$savedReport): bool {
                $savedReport = $report;

                return true;
            }));

        $useCase = new ReportContentUseCase($userRepository, $reportRepository);
        $useCase->execute(1, 'discussion', 99, 'Propos déplacés');

        self::assertSame('discussion', $savedReport->reportedContentType());
        self::assertSame(99, $savedReport->reportedContentId());
    }

    public function testReportsAReview(): void
    {
        $userRepository = $this->createStub(UserRepositoryInterface::class);
        $reportRepository = $this->createMock(ReportRepositoryInterface::class);

        $userRepository->method('ofId')->willReturn($this->reporter);
        $reportRepository->expects(self::once())->method('save');

        $useCase = new ReportContentUseCase($userRepository, $reportRepository);
        $useCase->execute(1, 'review', 5, 'Avis injurieux');
    }

    public function testThrowsForInvalidContentType(): void
    {
        $userRepository = $this->createStub(UserRepositoryInterface::class);
        $reportRepository = $this->createMock(ReportRepositoryInterface::class);

        $userRepository->method('ofId')->willReturn($this->reporter);
        $reportRepository->expects(self::never())->method('save');

        $this->expectException(\InvalidArgumentException::class);

        $useCase = new ReportContentUseCase($userRepository, $reportRepository);
        $useCase->execute(1, 'user', 5, 'Signalement invalide');
    }

    public function testThrowsWhenReporterNotFound(): void
    {
        $userRepository = $this->createStub(UserRepositoryInterface::class);
        $reportRepository = $this->createStub(ReportRepositoryInterface::class);

        $userRepository->method('ofId')->willReturn(null);

        $this->expectException(EntityNotFoundException::class);

        $useCase = new ReportContentUseCase($userRepository, $reportRepository);
        $useCase->execute(404, 'discussion', 5, 'Signalement');
    }
}
