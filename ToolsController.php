<?php

namespace App\Controller\base;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Doctrine\ORM\EntityManagerInterface;
use App\Service\base\FileUploader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Config\Definition\Exception\Exception;
use App\Service\base\ArrayHelper;
use Liip\ImagineBundle\Service\FilterService;
use App\Service\base\TestHelper;
use App\Security\EmailVerifier;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class ToolsController extends AbstractController
{
    //implémentation pour tous les controllers
    //RegistrationController comme base ;-)
    private EmailVerifier $emailVerifier;

    protected $logger;

    protected $translator;

    public function __construct(EmailVerifier $emailVerifier, LoggerInterface $logger, TranslatorInterface $translator)
    {
        $this->emailVerifier = $emailVerifier;
        $this->logger = $logger;
        $this->translator = $translator;
    }

    //accès à la partie admin du site
    #[Route('/admin', name: 'admin_index')]
    public function admin(): Response
    {
        if ($this->getUser() == null) {
            return $this->redirectToRoute('home_index');
        }

        return $this->render('admin/admin.html.twig', []);
    }

    /* -------------------------------------------------------------------------- */
    /*                        ajax pour uploader un fichier                       */
    /* -------------------------------------------------------------------------- */

    /**
     * > Uploads a file to the server, and returns the URL of the uploaded file
     * 
     * @param FilterService filterService The service that filters the image
     * @param FileUploader fileUploader The service that handles the file upload.
     * @param Request request The current request object.
     * @param string name The name of the directory where the file will be saved.
     * @param filter The name of the filter to apply to the image.
     */
    #[Route('/upload/{name}/{filter}', name: 'upload')]
    public function upload(FilterService $filterService, FileUploader $fileUploader, Request $request, string $name, $filter = null): Response
    {
        if ($request->files->get('upload')) {
            $filename = $fileUploader->upload($request->files->get('upload'), $name . '/', $filter);
            return new JsonResponse(['url' => '/' . $filename]);
        } else {
            return new JsonResponse(['error' => 'not get file']);
        }
    }



    /* ------------ permet de sélectionner dans une entité un élément ----------- */
    /* ------ et d'avoir une url coper dans le presse papier par sélection ------ */
    #[Route('/admin/SelectAndCopy/{entitie}/{champs}/{recherche}/{affichage}/{copy}/{limit}', name: 'selectandcopy')]
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

    /* -------------------------------------------------------------------------- */
    /*                testeur de liens et création du fichier json                */
    /* -------------------------------------------------------------------------- */
    #[Route('/superadmin/linktester', name: 'linktester')]
    public function linktester()
    {
        $retour = exec('php /app/
        fink.phar "http://localhost" --concurrency 12 --output=/app/tests/linktests.json --exclude-url=_profilerecho ');
        return new JsonResponse($retour);
    }
    /* -------------------------------------------------------------------------- */
    /*           Sert pour les indexs pour changer l'ordre des éléments           */
    /* -------------------------------------------------------------------------- */
    #[route('/admin/changeordre/{entity}/{id}/{action}', name: 'change_ordre', methods: ['GET'])]
    /**
     * It moves an element in an array to a new position
     * 
     * @param EntityManagerInterface em the entity manager
     * @param String entity the entity name
     * @param int id the id of the entity to move
     * @param String action the action to perform, up, down, top, bottom
     * 
     * @return Response A Response object
     */
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
}
