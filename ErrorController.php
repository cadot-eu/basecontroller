<?php

namespace App\Controller\base;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Controller\base\ToolsController;
use App\Service\base\IpHelper;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ErrorController extends ToolsController
{
    #[Route('/error', name: 'app_error')]
    public function show($exception, LoggerInterface $logger, HttpClientInterface $httpClient, Request $request): Response
    {
$iphelper = new IpHelper($httpClient);
$ip = $iphelper->getIp();
        $urlNotSave=['/.well-known/traffic-advice'];
        if ($_ENV['APP_ENV'] != 'dev') {
            if ($exception->getStatusCode() == 404 && !in_array($_SERVER['REQUEST_URI'], $urlNotSave)) {
                $error = json_decode(file_get_contents('/app/404.json'), true);
                $helperip = new IpHelper($httpClient);
                $infos = $helperip->getInformations();
              
                $error[$_SERVER['REQUEST_URI']][] = [
                    'user' => $this->getUser() ? $this->getUser()->getId() : null,
                    'ip' => $ip,
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
                    'referer' => isset($_SESSION['referer']) ? $_SESSION['referer'] : null,
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
        } else {
            return $this->render('errors.html.twig', [
                'controller_name' => 'ErrorController',
                'message' => $exception->getMessage(),
                'code' => $exception->getStatusCode(),
            ]);
        }
    }
}
