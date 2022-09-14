<?php

namespace App\Controller\base;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Doctrine\ORM\EntityManagerInterface;
use App\Service\base\FileUploader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ToolsController extends AbstractController
{
    #[Route('/admin', name: 'admin_index')]
    public function admin(): Response
    {
        if ($this->getUser() == null) {
            return $this->redirectToRoute('home_index');
        }
        if ($this->getUser()->isVerified()) {
            return $this->render('admin/accountvalidated.html.twig', []);
        }
        return $this->render('admin/accountnotvalidated.html.twig', []);
    }

    //ajax
    #[Route('/upload/{name}', name: 'upload')]
    public function upload(FileUploader $fileUploader, Request $request, string $name): Response
    {

        return new JsonResponse(['url' => '/' . $fileUploader->upload($request->files->get('upload'), $name . '/')]);
    }
    #[Route('/admin/SelectAndCopy/{entitie}/{champs}/{recherche}/{affichage}/{copy}/{limit}', name: 'linktester')]
    /**
     * It searches for a string in the title of an article, and returns the title and the content of
     * the article
     * 
     * @param EntityManagerInterface em
     * @param entitie the name of the entity you want to search in
     * @param champs the field to search in
     * @param recherche the search term
     * @param affichage the field to display in the autocomplete
     * @param copy the field to copy
     * @param limit the number of results to return
     */
    public function SAndCopy(EntityManagerInterface $em, $entitie, $champs, $recherche, $affichage, $copy, $limit)
    {
        //recherche dans les titres
        $query = $em->createQuery(
            'SELECT p
            FROM App\Entity\\' . ucfirst($entitie) . ' p
            WHERE p.' . $champs . ' LIKE :recherche'
        )->setParameter('recherche', '%' . $recherche . '%');
        $entities = array_slice($query->getResult(), 0, $limit);
        $tablo = [];
        $getaffichage = 'get' . ucwords($affichage);
        $getcopy = 'get' . ucwords($copy);
        foreach ($entities as $entity) {
            $tablo[] = ['affichage' => $entity->$getaffichage(), 'copy' => $entity->$getcopy()];
        }
        return new JsonResponse($tablo);
    }
    #[Route('/admin/linktester', name: 'linktester')]
    public function linktester()
    {
        $retour = exec('php /app/
        fink.phar "http://localhost" --concurrency 12 --output=/app/tests/linktests.json --exclude-url=_profilerecho ');
        return new JsonResponse($retour);
    }
}
