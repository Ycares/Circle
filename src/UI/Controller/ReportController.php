<?php

declare(strict_types=1);

namespace App\UI\Controller;

use App\Application\UseCase\ReportContent\ReportContentUseCase;
use App\Domain\Exception\EntityNotFoundException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class ReportController extends AbstractController
{
    use CurrentUserTrait;

    public function __construct(private readonly ReportContentUseCase $reportContentUseCase)
    {
    }

    #[Route('/reports', name: 'app_report_content', methods: ['POST'])]
    public function __invoke(Request $request): Response
    {
        $redirectTo = (string) $request->request->get('redirectTo', '/profile');

        // N'accepte que des chemins locaux relatifs : la valeur vient d'un champ caché du
        // formulaire, mais on se protège quand même d'une redirection ouverte forgée.
        if (!str_starts_with($redirectTo, '/') || str_starts_with($redirectTo, '//')) {
            $redirectTo = '/profile';
        }

        if (!$this->isCsrfTokenValid('report_content', $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide, merci de réessayer.');

            return $this->redirect($redirectTo);
        }

        $contentType = (string) $request->request->get('contentType', '');
        $contentId = (int) $request->request->get('contentId', 0);
        $reason = trim((string) $request->request->get('reason', ''));

        if ('' === $reason) {
            $this->addFlash('error', 'Merci de préciser une raison pour ce signalement.');

            return $this->redirect($redirectTo);
        }

        try {
            $this->reportContentUseCase->execute($this->currentUser()->id(), $contentType, $contentId, $reason);
            $this->addFlash('success', 'Votre signalement a été envoyé.');
        } catch (EntityNotFoundException|\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirect($redirectTo);
    }
}
