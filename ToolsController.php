<?php

namespace App\Controller\base;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\EntityManagerInterface;
use App\Service\base\ArrayHelper;
use Symfony\Component\Config\Definition\Exception\Exception;

class ToolsController extends AbstractController
{
    #[route('/admin/changeordre/{entity}/{id}/{action}', name: 'change_ordre', methods: ['GET'])]
    public function changeOrdre(EntityManagerInterface $em, String $entity, int $id, String $action): Response
    {
        $faqs = $em->getRepository('App\\Entity\\' . ucwords($entity))->findBy([], ['ordre' => 'ASC']);
        foreach ($faqs as $num => $faq) {
            if ($faq->getId() == $id) {
                $pos = $num;
            }
        }
        switch ($action) {
            case 'up':
                $dest = $pos - 1;
                break;
            case 'down':
                $dest = $pos + 1;
                break;
            case 'top':
                $dest = 0;
                break;
            case 'bottom':
                $dest = count($faqs) - 1;
                break;
            default:
                throw new Exception('Mouvement inconnu, up, top, down, bottom');
                break;
        }
        foreach (ArrayHelper::moveElement($faqs, $pos, $dest) as $num => $faq) {
            $faq->setOrdre($num);
            $em->persist($faq);
        }
        $em->flush();
        return $this->redirectToRoute(strtolower($entity) . '_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/admin/links', name: 'test_links')]
    public function test_links()
    {
        // exec('php /app/fink.phar "http://localhost" --concurrency 12 --output=/app/linktests.json >/dev/null 2>&1 &');
        foreach (explode("\n", file_get_contents('/app/linktests.json')) as $t) {
            $tab[] = json_decode($t, true);
        }
        return $this->renderForm(
            'base/test_links.html.twig',
            [
                'links' => $tab,
            ]
        );
    }
}
