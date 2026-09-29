<?php

declare(strict_types=1);

namespace App\UI\Controller;

use App\Application\Port\BookMetadataProviderInterface;
use App\Application\UseCase\AddBookToLibrary\AddBookToLibraryUseCase;
use App\Application\UseCase\UpdateReadingProgress\UpdateReadingProgressUseCase;
use App\Domain\Exception\BookAlreadyInLibraryException;
use App\Domain\Exception\EntityNotFoundException;
use App\Domain\Exception\UnauthorizedActionException;
use App\Domain\Repository\UserBookRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class LibraryController extends AbstractController
{
    use CurrentUserTrait;
    use ParsesPatchPayloadTrait;

    public function __construct(
        private readonly UserBookRepositoryInterface $userBookRepository,
        private readonly BookMetadataProviderInterface $bookMetadataProvider,
        private readonly AddBookToLibraryUseCase $addBookToLibraryUseCase,
        private readonly UpdateReadingProgressUseCase $updateReadingProgressUseCase,
    ) {
    }

    #[Route('/library', name: 'app_library_index', methods: ['GET'])]
    public function index(): Response
    {
        $userBooks = $this->userBookRepository->ofUser($this->currentUser());

        return $this->render('library/index.html.twig', [
            'userBooks' => $userBooks,
        ]);
    }

    #[Route('/library/search', name: 'app_library_search', methods: ['POST'])]
    public function search(Request $request): Response
    {
        $query = trim((string) $request->request->get('q', ''));

        if (!$this->isCsrfTokenValid('library_search', $request->request->get('_token'))) {
            return $this->render('library/search_results.html.twig', ['results' => [], 'query' => $query]);
        }

        $results = '' === $query ? [] : $this->bookMetadataProvider->search($query);

        return $this->render('library/search_results.html.twig', [
            'results' => $results,
            'query' => $query,
        ]);
    }

    #[Route('/library/books', name: 'app_library_add_book', methods: ['POST'])]
    public function addBook(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('library_add_book', $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide, merci de réessayer.');

            return $this->redirectToRoute('app_library_index');
        }

        $externalId = (string) $request->request->get('externalId', '');
        $details = '' === $externalId ? null : $this->bookMetadataProvider->fetchById($externalId);

        if (null === $details) {
            $this->addFlash('error', "Impossible de récupérer les informations de ce livre, merci de réessayer.");

            return $this->redirectToRoute('app_library_index');
        }

        try {
            $this->addBookToLibraryUseCase->execute(
                $this->currentUser()->id(),
                $details->externalId,
                $details->title,
                $details->authors,
                $details->language,
                $details->coverUrl,
                $details->genres,
            );

            $this->addFlash('success', \sprintf('« %s » a été ajouté à votre bibliothèque.', $details->title));
        } catch (BookAlreadyInLibraryException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_library_index');
    }

    #[Route('/library/books/{id}/progress', name: 'app_library_update_progress', methods: ['PATCH'], requirements: ['id' => '\d+'])]
    public function updateProgress(Request $request, int $id): Response
    {
        $payload = $this->parsePatchPayload($request);

        if (!$this->isCsrfTokenValid('library_update_progress', $payload['_token'] ?? null)) {
            $this->addFlash('error', 'Jeton de sécurité invalide, merci de réessayer.');

            return $this->redirectToRoute('app_library_index');
        }

        try {
            $this->updateReadingProgressUseCase->execute($id, $this->currentUser()->id(), (int) ($payload['progress'] ?? 0));
        } catch (EntityNotFoundException|UnauthorizedActionException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_library_index');
    }
}
