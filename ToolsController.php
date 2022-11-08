<?php

namespace App\Controller\base;

use App\Entity\Chatmessage;
use App\Repository\ChatRepository;
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
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Contracts\Translation\TranslatorInterface;
use App\Entity\Chat;
use Symfony\Component\Serializer\SerializerInterface;

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
     * @param FilterService filterService The service that will be used to filter the image.
     * @param FileUploader fileUploader The service that handles the file upload.
     * @param Request request The request object
     * @param string name The name of the folder to upload to.
     * @param filter The name of the filter to apply to the image.
     * 
     * @return Response A JSON response with the URL of the uploaded file.
     */
    #[Route('/upload/{name}/{filter}', name: 'upload')]
    public function upload(FilterService $filterService, FileUploader $fileUploader, Request $request, string $name, $filter = null): Response
    {
        $filename = $fileUploader->upload($request->files->get('upload'), $name . '/', $filter);
        return new JsonResponse(['url' => '/' . $filename]);
    }
    /* -------------------------------------------------------------------------- */
    /*                        ajax pour simplegallery                       */
    /* -------------------------------------------------------------------------- */

    /**
     * It takes a file upload, uploads it to the server, and returns a JSON response with the URLs of
     * the uploaded file and its resized versions
     * 
     * @param FilterService filterService The service that will be used to filter the image.
     * @param FileUploader fileUploader The service that handles the file upload.
     * @param Request request The request object
     * @param string name the name of the folder where the images will be stored
     * @param filter the name of the filter to apply to the image.
     * 
     * @return Response An array of urls to the images.
     */
    #[Route('/simplegallery/{name}/{filter}', name: 'simplegallery')]
    public function simplegallery(FilterService $filterService, FileUploader $fileUploader, Request $request, string $name, $filter = null): Response
    {
        $filename = $fileUploader->upload($request->files->get('upload'), $name . '/', $filter);
        $widths = [32, 128, 300, 600, 1080, 1920];
        foreach ($widths as $width) {
            $temp = $filterService->getUrlOfFilteredImage($filename, $width);
            $destDir[$width] = str_replace('http://', 'https://', $temp);
        }
        return new JsonResponse(['urls' => $destDir]);
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
    #[route('/chatSend/{user}', name: 'chatsend', methods: ['POST'])]
    function chatsend(ChatRepository $chatRepository, Request $request, EntityManagerInterface $em)
    {
        $content = (json_decode($request->getContent()));
        $chat = $chatRepository->findOneBy(['user' => $request->get('user')]);
        if (!$chat) {
            $chat = new Chat();
            $chat->setUser($request->get('user'));
        }
        $chat->setDeletedAt(null); //on réactive l'archive
        $chat->setupdatedAt(new \DateTime());
        $chatmessage = new Chatmessage();
        $chatmessage->setTexte($content->message);
        $chatmessage->setType($content->type);
        $chat->addMessage($chatmessage);
        $em->persist($chat);
        $em->flush();
        return new JsonResponse(['message' => 'ok']);
    }
    #[route('/chatGetMessages/{user}', name: 'chatGetMessages', methods: ['GET'])]
    function chatgetmessages(ChatRepository $chatRepository, Request $request, SerializerInterface $serializer)
    {
        $chat = $chatRepository->findOneBy(['user' => $request->get('user'), 'deletedAt' => null]);
        $retour = [];
        if ($chat) {
            $messages = $chat->getMessages();
            foreach ($messages as $message) {
                $retour[] = [
                    'texte' => $message->getTexte(),
                    'date' => $message->getCreatedAt() ? $message->getCreatedAt()->format('d/m/Y H:i:s') : '',
                    'type' => $message->getType()
                ];
            }
        }
        return new JsonResponse(array_reverse($retour));
    }
    #[Route('/admin/chatboxs', name: 'chatboxs_index', methods: ['GET'])]
    public function chatboxs_index(ChatRepository $chatRepository, Request $request): Response
    {
        return $this->render('/base/chatbox_index.html.twig', [
            'chats' => $chatRepository->findBy(['deletedAt' => null])
        ]);
    }
}
