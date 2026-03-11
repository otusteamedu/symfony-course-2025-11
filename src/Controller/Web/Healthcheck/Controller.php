<?php

namespace App\Controller\Web\Healthcheck;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
class Controller
{
    #[Route(path: '/healthcheck', name: 'healthcheck')]
    public function __invoke(): Response
    {
        return new JsonResponse(['ok']);
    }
}