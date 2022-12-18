<?php

namespace App\Controller\base;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use App\Controller\base\ToolsController;

class ErrorController extends ToolsController
{
    #[Route('/error', name: 'app_error')]
    public function show($exception): Response
    {
        return $this->render('errors.html.twig', [
            'controller_name' => 'ErrorController',
            'message' => $exception->getMessage(),
            'code' => $exception->getStatusCode(),
        ]);
    }
}
