<?php

declare(strict_types=1);

namespace App\UI\Controller;

use App\Application\Port\BookMetadataProviderInterface;
use App\Application\UseCase\CreateReadingClub\CreateReadingClubUseCase;
use App\Application\UseCase\DeclareClubProgress\DeclareClubProgressUseCase;
use App\Application\UseCase\JoinReadingClub\JoinReadingClubUseCase;
use App\Application\UseCase\SetClubBook\SetClubBookUseCase;
use App\Application\UseCase\UpdateChapterCount\UpdateChapterCountUseCase;
use App\Domain\Entity\ClubMembership;
use App\Domain\Entity\ReadingClub;
use App\Domain\Exception\AlreadyClubMemberException;
use App\Domain\Exception\ClubNotJoinableException;
use App\Domain\Exception\EntityNotFoundException;
use App\Domain\Exception\UnauthorizedActionException;
use App\Domain\Repository\ChapterRepositoryInterface;
use App\Domain\Repository\ClubMembershipRepositoryInterface;
use App\Domain\Repository\ReadingClubRepositoryInterface;
use App\Domain\ValueObject\ClubVisibility;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class ClubController extends AbstractController
{
    use CurrentUserTrait;
    use ParsesPatchPayloadTrait;

    public function __construct(
        private readonly ReadingClubRepositoryInterface $readingClubRepository,
        private readonly ChapterRepositoryInterface $chapterRepository,
        private readonly ClubMembershipRepositoryInterface $clubMembershipRepository,
        private readonly BookMetadataProviderInterface $bookMetadataProvider,
        private readonly CreateReadingClubUseCase $createReadingClubUseCase,
        private readonly SetClubBookUseCase $setClubBookUseCase,
        private readonly UpdateChapterCountUseCase $updateChapterCountUseCase,
        private readonly JoinReadingClubUseCase $joinReadingClubUseCase,
        private readonly DeclareClubProgressUseCase $declareClubProgressUseCase,
    ) {
    }

    #[Route('/clubs', name: 'app_club_index', methods: ['GET'])]
    public function index(): Response
    {
        $user = $this->currentUser();
        $memberships = $this->clubMembershipRepository->ofUser($user);
        $myClubIds = array_map(static fn (ClubMembership $membership): ?int => $membership->readingClub()->id(), $memberships);

        $allClubs = $this->readingClubRepository->all();
        $myClubs = array_values(array_filter($allClubs, static fn (ReadingClub $club): bool => \in_array($club->id(), $myClubIds, true)));
        $publicClubs = array_values(array_filter(
            $allClubs,
            static fn (ReadingClub $club): bool => ClubVisibility::PUBLIC === $club->visibility() && !\in_array($club->id(), $myClubIds, true),
        ));

        return $this->render('club/index.html.twig', [
            'myClubs' => $myClubs,
            'publicClubs' => $publicClubs,
        ]);
    }

    #[Route('/clubs', name: 'app_club_create', methods: ['POST'])]
    public function create(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('club_create', $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide, merci de réessayer.');

            return $this->redirectToRoute('app_club_index');
        }

        $name = trim((string) $request->request->get('name', ''));
        $description = trim((string) $request->request->get('description', ''));
        $visibility = (string) $request->request->get('visibility', '');

        if (null === ClubVisibility::tryFrom($visibility)) {
            $this->addFlash('error', 'Visibilité de club invalide.');

            return $this->redirectToRoute('app_club_index');
        }

        $this->createReadingClubUseCase->execute($this->currentUser()->id(), $name, $description, $visibility);
        $this->addFlash('success', \sprintf('Le club « %s » a été créé.', $name));

        return $this->redirectToRoute('app_club_index');
    }

    #[Route('/clubs/{id}', name: 'app_club_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id): Response
    {
        $readingClub = $this->readingClubRepository->ofId($id);

        if (null === $readingClub) {
            throw $this->createNotFoundException('Club introuvable.');
        }

        $membership = $this->clubMembershipRepository->ofUserAndClub($this->currentUser(), $readingClub);
        $chapters = null !== $readingClub->currentBook() ? $this->chapterRepository->ofReadingClub($readingClub) : [];
        $members = $this->clubMembershipRepository->ofReadingClub($readingClub);

        return $this->render('club/show.html.twig', [
            'club' => $readingClub,
            'membership' => $membership,
            'isHost' => $readingClub->host()->id() === $this->currentUser()->id(),
            'chapters' => $chapters,
            'members' => $members,
        ]);
    }

    #[Route('/clubs/{id}/book-search', name: 'app_club_book_search', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function searchBook(Request $request, int $id): Response
    {
        $query = trim((string) $request->request->get('q', ''));

        if (!$this->isCsrfTokenValid('club_book_search', $request->request->get('_token'))) {
            return $this->render('club/_book_search_results.html.twig', ['results' => [], 'query' => $query, 'club' => $this->requireClub($id)]);
        }

        $results = '' === $query ? [] : $this->bookMetadataProvider->search($query);

        return $this->render('club/_book_search_results.html.twig', [
            'results' => $results,
            'query' => $query,
            'club' => $this->requireClub($id),
        ]);
    }

    #[Route('/clubs/{id}/book', name: 'app_club_set_book', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function setBook(Request $request, int $id): Response
    {
        if (!$this->isCsrfTokenValid('club_set_book', $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide, merci de réessayer.');

            return $this->redirectToRoute('app_club_show', ['id' => $id]);
        }

        $externalId = (string) $request->request->get('externalId', '');
        $chapterCount = (int) $request->request->get('chapterCount', 0);
        $details = '' === $externalId ? null : $this->bookMetadataProvider->fetchById($externalId);

        if (null === $details || $chapterCount < 1) {
            $this->addFlash('error', 'Impossible de définir ce livre, merci de réessayer.');

            return $this->redirectToRoute('app_club_show', ['id' => $id]);
        }

        try {
            $this->setClubBookUseCase->execute(
                $id,
                $this->currentUser()->id(),
                $details->externalId,
                $details->title,
                $details->authors,
                $details->language,
                $details->coverUrl,
                $details->genres,
                $chapterCount,
            );

            $this->addFlash('success', \sprintf('« %s » est maintenant le livre en cours du club.', $details->title));
        } catch (EntityNotFoundException|UnauthorizedActionException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_club_show', ['id' => $id]);
    }

    #[Route('/clubs/{id}/chapter-count', name: 'app_club_update_chapter_count', methods: ['PATCH'], requirements: ['id' => '\d+'])]
    public function updateChapterCount(Request $request, int $id): Response
    {
        $payload = $this->parsePatchPayload($request);

        if (!$this->isCsrfTokenValid('club_update_chapter_count', $payload['_token'] ?? null)) {
            $this->addFlash('error', 'Jeton de sécurité invalide, merci de réessayer.');

            return $this->redirectToRoute('app_club_show', ['id' => $id]);
        }

        try {
            $this->updateChapterCountUseCase->execute($id, $this->currentUser()->id(), (int) ($payload['chapterCount'] ?? 0));
            $this->addFlash('success', 'Le nombre de chapitres a été mis à jour.');
        } catch (EntityNotFoundException|UnauthorizedActionException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_club_show', ['id' => $id]);
    }

    #[Route('/clubs/{id}/join', name: 'app_club_join', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function join(Request $request, int $id): Response
    {
        if (!$this->isCsrfTokenValid('club_join', $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide, merci de réessayer.');

            return $this->redirectToRoute('app_club_show', ['id' => $id]);
        }

        try {
            $this->joinReadingClubUseCase->execute($this->currentUser()->id(), $id);
            $this->addFlash('success', 'Vous avez rejoint le club.');
        } catch (EntityNotFoundException|AlreadyClubMemberException|ClubNotJoinableException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_club_show', ['id' => $id]);
    }

    #[Route('/clubs/{id}/progress', name: 'app_club_declare_progress', methods: ['PATCH'], requirements: ['id' => '\d+'])]
    public function declareProgress(Request $request, int $id): Response
    {
        $payload = $this->parsePatchPayload($request);

        if (!$this->isCsrfTokenValid('club_declare_progress', $payload['_token'] ?? null)) {
            $this->addFlash('error', 'Jeton de sécurité invalide, merci de réessayer.');

            return $this->redirectToRoute('app_club_show', ['id' => $id]);
        }

        try {
            $this->declareClubProgressUseCase->execute($this->currentUser()->id(), $id, (int) ($payload['declaredChapter'] ?? 0));
            $this->addFlash('success', 'Votre progression a été mise à jour.');
        } catch (EntityNotFoundException|\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_club_show', ['id' => $id]);
    }

    private function requireClub(int $id): ReadingClub
    {
        $readingClub = $this->readingClubRepository->ofId($id);

        if (null === $readingClub) {
            throw $this->createNotFoundException('Club introuvable.');
        }

        return $readingClub;
    }
}
