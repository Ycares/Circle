<?php

declare(strict_types=1);

namespace App\UI\Controller;

use App\Domain\Entity\UserBook;
use App\Domain\Repository\ClubMembershipRepositoryInterface;
use App\Domain\Repository\DiscussionRepositoryInterface;
use App\Domain\Repository\ReportRepositoryInterface;
use App\Domain\Repository\UserBookRepositoryInterface;
use App\Domain\ValueObject\BookStatus;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class ProfileController extends AbstractController
{
    use CurrentUserTrait;

    public function __construct(
        private readonly UserBookRepositoryInterface $userBookRepository,
        private readonly ClubMembershipRepositoryInterface $clubMembershipRepository,
        private readonly ReportRepositoryInterface $reportRepository,
        private readonly DiscussionRepositoryInterface $discussionRepository,
    ) {
    }

    #[Route('/profile', name: 'app_profile', methods: ['GET'])]
    public function index(): Response
    {
        $user = $this->currentUser();

        $userBooks = $this->userBookRepository->ofUser($user);
        $booksRead = \count(array_filter($userBooks, static fn (UserBook $userBook): bool => BookStatus::READ === $userBook->status()));
        $clubsJoined = \count($this->clubMembershipRepository->ofUser($user));

        return $this->render('profile/index.html.twig', [
            'user' => $user,
            'booksRead' => $booksRead,
            'clubsJoined' => $clubsJoined,
        ]);
    }

    #[Route('/profile/reports', name: 'app_profile_reports', methods: ['GET'])]
    public function reports(): Response
    {
        $user = $this->currentUser();

        // Seuls les signalements de discussion peuvent être rattachés à un club (et donc à
        // son hôte) : un Report ne porte qu'un type + un id, sans relation directe.
        $items = [];
        foreach ($this->reportRepository->all() as $report) {
            if ('discussion' !== $report->reportedContentType()) {
                continue;
            }

            $discussion = $this->discussionRepository->ofId($report->reportedContentId());

            if (null === $discussion || $discussion->readingClub()->host()->id() !== $user->id()) {
                continue;
            }

            $items[] = ['report' => $report, 'discussion' => $discussion];
        }

        usort($items, static fn (array $a, array $b): int => $b['report']->createdAt() <=> $a['report']->createdAt());

        return $this->render('profile/reports.html.twig', [
            'items' => $items,
        ]);
    }
}
