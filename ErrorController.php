<?php

namespace App\Controller\base;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Controller\base\ToolsController;
use Psr\Log\LoggerInterface;

class ErrorController extends ToolsController
{



    #[Route('/error', name: 'app_error')]
    public function show($exception, LoggerInterface $logger): Response
    {
        if ($_ENV['APP_ENV'] == 'dev') {
            dd($exception);
        } else {
            $this->logger->error($exception->getMessage());
            return $this->render('errors.html.twig', [
                'controller_name' => 'ErrorController',
                'message' => $exception->getMessage(),
                'code' => $exception->getStatusCode(),
            ]);
        }
    }
}
