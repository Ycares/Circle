<?php

declare(strict_types=1);

namespace App\UI\Controller;

use Symfony\Component\HttpFoundation\Request;

/**
 * PHP ne peuple jamais $_POST pour une requête PATCH : le corps doit être lu et
 * décodé manuellement (form-urlencoded ou JSON selon le Content-Type). En test
 * fonctionnel, BrowserKit peuple directement $request->request, d'où la première
 * vérification.
 */
trait ParsesPatchPayloadTrait
{
    /**
     * @return array<string, mixed>
     */
    private function parsePatchPayload(Request $request): array
    {
        if ($request->request->count() > 0) {
            return $request->request->all();
        }

        $content = $request->getContent();

        if ('' === $content) {
            return [];
        }

        if (str_contains((string) $request->headers->get('Content-Type'), 'application/json')) {
            $decoded = json_decode($content, true);

            return \is_array($decoded) ? $decoded : [];
        }

        parse_str($content, $data);

        return $data;
    }
}
