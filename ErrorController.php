<?php

namespace App\Controller\base;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Controller\base\ToolsController;
use App\Service\base\IpHelper;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ErrorController extends ToolsController
{
    #[Route('/error', name: 'app_error')]
    public function show($exception, LoggerInterface $logger, HttpClientInterface $httpClient): Response
    {
        if ($_ENV['APP_ENV'] == 'dd') {
            dd($exception);
        } else {
            if ($exception->getStatusCode() == 404) {
                $error = json_decode(file_get_contents('/app/404.json'), true);
                $helperip = new IpHelper($httpClient);
                $infos = $helperip->getInformations();
                $error[$_SERVER['REQUEST_URI']][] = [
                    'referer' => $_SERVER['HTTP_REFERER'] ?? null,
                    'user' => $this->getUser() ? $this->getUser()->getId() : null,
                    'ip' => $_SERVER['REMOTE_ADDR'],
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'],
                    'country' => $infos['country'],
                    'city' => $infos['city'],
                    'isp' => $infos['isp'],
                    'lat' => $infos['lat'],
                    'lon' => $infos['lon'],
                    'timezone' => $infos['timezone'],
                    'zip' => $infos['zip'],
                    'query' => $infos['query'],
                    'regionName' => $infos['regionName'],
                    'url' => $_SERVER['REQUEST_URI'],
                    'date' => date('Y-m-d H:i:s'),
                ];
                file_put_contents('/app/404.json', json_encode($error, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            } else {
                $this->logger->error($exception->getMessage());
            }
            return $this->render('errors.html.twig', [
                'controller_name' => 'ErrorController',
                'message' => $exception->getMessage(),
                'code' => $exception->getStatusCode(),
            ]);
        }
    }
}
