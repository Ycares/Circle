<?php

declare(strict_types=1);

namespace App\UI\Controller;

use App\Application\UseCase\ReviewBook\ReviewBookUseCase;
use App\Domain\Exception\EntityNotFoundException;
use App\Domain\Exception\UnauthorizedActionException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class ReviewController extends AbstractController
{
    use CurrentUserTrait;

    public function __construct(private readonly ReviewBookUseCase $reviewBookUseCase)
    {
    }

    #[Route('/library/books/{id}/review', name: 'app_library_review', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function __invoke(Request $request, int $id): Response
    {
        if (!$this->isCsrfTokenValid('library_review', $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide, merci de réessayer.');

            return $this->redirectToRoute('app_library_index');
        }

        $text = trim((string) $request->request->get('text', ''));
        $containsSpoiler = null !== $request->request->get('containsSpoiler');

        if ('' === $text) {
            $this->addFlash('error', "Le texte de l'avis ne peut pas être vide.");

            return $this->redirectToRoute('app_library_index');
        }

        try {
            $this->reviewBookUseCase->execute($id, $this->currentUser()->id(), $text, $containsSpoiler);
            $this->addFlash('success', 'Votre avis a été publié.');
        } catch (EntityNotFoundException|UnauthorizedActionException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_library_index');
    }
}
