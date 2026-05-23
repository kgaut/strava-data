<?php

declare(strict_types=1);

namespace App\Controller;

use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class SecurityController extends AbstractController
{
    #[Route('/login', name: 'app_login')]
    public function login(): Response
    {
        if (null !== $this->getUser()) {
            return $this->redirectToRoute('app_dashboard');
        }

        return $this->render('security/login.html.twig');
    }

    #[Route('/connect/strava', name: 'connect_strava_start')]
    public function connectStart(ClientRegistry $clientRegistry): RedirectResponse
    {
        return $clientRegistry
            ->getClient('strava')
            ->redirect(['read', 'activity:read_all', 'profile:read_all'], []);
    }

    #[Route('/connect/strava/check', name: 'connect_strava_check')]
    public function connectCheck(): Response
    {
        // The actual logic lives in StravaAuthenticator::authenticate().
        // Reaching this controller means authentication did not redirect,
        // which only happens when the user is already logged in.
        return $this->redirectToRoute('app_dashboard');
    }

    #[Route('/logout', name: 'app_logout')]
    public function logout(): never
    {
        throw new \LogicException('This method can be blank — it will be intercepted by the logout key on your firewall.');
    }
}
