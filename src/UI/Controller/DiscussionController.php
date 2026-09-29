<?php

declare(strict_types=1);

namespace App\UI\Controller;

use App\Application\UseCase\ListVisibleDiscussions\ListVisibleDiscussionsUseCase;
use App\Application\UseCase\PostDiscussionMessage\PostDiscussionMessageUseCase;
use App\Domain\Entity\Discussion;
use App\Domain\Exception\EntityNotFoundException;
use App\Domain\Exception\UnauthorizedActionException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class DiscussionController extends AbstractController
{
    use CurrentUserTrait;

    public function __construct(
        private readonly ListVisibleDiscussionsUseCase $listVisibleDiscussionsUseCase,
        private readonly PostDiscussionMessageUseCase $postDiscussionMessageUseCase,
    ) {
    }

    #[Route('/clubs/{id}/discussions', name: 'app_club_discussions', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function index(Request $request, int $id): Response
    {
        $chapterId = $request->query->get('chapter');
        $chapterId = null !== $chapterId && '' !== $chapterId ? (int) $chapterId : null;

        try {
            // Le filtrage anti-spoiler est déjà appliqué par le Use Case : cette liste ne
            // contient que ce que l'utilisateur connecté est autorisé à voir.
            $discussions = $this->listVisibleDiscussionsUseCase->execute($this->currentUser()->id(), $id);
        } catch (EntityNotFoundException) {
            return $this->render('club/_discussions.html.twig', [
                'clubId' => $id,
                'chapterId' => $chapterId,
                'discussions' => [],
                'error' => 'Vous devez être membre de ce club pour voir ses discussions.',
            ]);
        }

        $discussions = array_values(array_filter(
            $discussions,
            static fn (Discussion $discussion): bool => $discussion->chapter()?->id() === $chapterId,
        ));

        return $this->render('club/_discussions.html.twig', [
            'clubId' => $id,
            'chapterId' => $chapterId,
            'discussions' => $discussions,
            'error' => null,
        ]);
    }

    #[Route('/clubs/{id}/discussions', name: 'app_club_post_discussion', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function post(Request $request, int $id): Response
    {
        $chapterIdRaw = (string) $request->request->get('chapterId', '');
        $chapterId = '' === $chapterIdRaw ? null : (int) $chapterIdRaw;
        $text = trim((string) $request->request->get('text', ''));

        if ($this->isCsrfTokenValid('club_post_discussion', $request->request->get('_token')) && '' !== $text) {
            try {
                $this->postDiscussionMessageUseCase->execute($this->currentUser()->id(), $id, $chapterId, $text);
            } catch (EntityNotFoundException|UnauthorizedActionException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->redirectToRoute('app_club_discussions', ['id' => $id, 'chapter' => $chapterId]);
    }
}
