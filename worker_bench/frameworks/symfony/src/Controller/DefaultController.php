<?php declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DefaultController
{
    #[Route('/', name: 'hello')]
    public function hello(): Response
    {
        return new Response('Hello World');
    }

    #[Route('/json', name: 'json')]
    public function json(): JsonResponse
    {
        return new JsonResponse(['time' => 0.0]);
    }
}
