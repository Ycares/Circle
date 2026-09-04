<?php

declare(strict_types=1);

namespace App\UI\Controller\Auth;

use App\Application\UseCase\RegisterUser\RegisterUserDTO;
use App\Application\UseCase\RegisterUser\RegisterUserUseCase;
use App\Domain\Exception\EmailAlreadyRegisteredException;
use App\Domain\Exception\InvalidEmailAddressException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final class RegisterController extends AbstractController
{
    public function __construct(private readonly RegisterUserUseCase $registerUserUseCase)
    {
    }

    #[Route('/register', name: 'app_register', methods: ['GET', 'POST'])]
    public function __invoke(Request $request, CsrfTokenManagerInterface $csrfTokenManager): Response
    {
        $errors = [];
        $email = (string) $request->request->get('email', '');
        $pseudo = (string) $request->request->get('pseudo', '');
        $preferredLanguage = (string) $request->request->get('preferredLanguage', 'fr');

        if ($request->isMethod('POST')) {
            $token = new CsrfToken('register', (string) $request->request->get('_token'));

            if (!$csrfTokenManager->isTokenValid($token)) {
                $errors[] = 'Jeton de sécurité invalide, merci de réessayer.';
            } else {
                try {
                    $this->registerUserUseCase->execute(new RegisterUserDTO(
                        email: $email,
                        plainPassword: (string) $request->request->get('password', ''),
                        pseudo: $pseudo,
                        preferredLanguage: $preferredLanguage,
                    ));

                    $this->addFlash('success', 'Votre compte a été créé, vous pouvez maintenant vous connecter.');

                    return $this->redirectToRoute('app_login');
                } catch (InvalidEmailAddressException $e) {
                    $errors[] = $e->getMessage();
                } catch (EmailAlreadyRegisteredException $e) {
                    $errors[] = $e->getMessage();
                }
            }
        }

        return $this->render('auth/register.html.twig', [
            'errors' => $errors,
            'email' => $email,
            'pseudo' => $pseudo,
            'preferredLanguage' => $preferredLanguage,
        ]);
    }
}
