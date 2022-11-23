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
use DateTime;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Mercure\Update;
use PHPMailer\PHPMailer\PHPMailer;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class ToolsController extends AbstractController
{
    //implémentation pour tous les controllers
    //RegistrationController comme base ;-)
    private EmailVerifier $emailVerifier;

    protected $logger, $translator, $em;

    public function __construct(EmailVerifier $emailVerifier, LoggerInterface $logger, TranslatorInterface $translator, EntityManagerInterface $em)
    {
        $this->emailVerifier = $emailVerifier;
        $this->logger = $logger;
        $this->translator = $translator;
        $this->em = $em;
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

    /* -------------------------------------------------------------------------- */
    /*                        ajax pour uploader un fichier                       */
    /* -------------------------------------------------------------------------- */

    #[Route('/flashmessage', name: 'flashmessage', methods: ['POST'])]
    public function flashmessage(request $request): Response
    {
        $content = (json_decode($request->getContent()));
        $this->addFlash($content->type, $content->message);
        return new response('ok');
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
    public function chatsend(ChatRepository $chatRepository, Request $request, EntityManagerInterface $em, HubInterface $hub)
    {
        $content = (json_decode($request->getContent()));
        $chat = $chatRepository->findOneBy(['user' => $request->get('user')]);
        if (!$chat) {
            $chat = new Chat();
            $chat->setUser($request->get('user'));
        }
        $chat->setDeletedAt(null); //on réactive l'archive
        $chatmessage = new Chatmessage();
        $chatmessage->setTexte($content->message);
        $chatmessage->setType($content->type);
        $chatmessage->setcreatedAt(new \DateTime());
        $chat->addMessage($chatmessage);
        $chat->setupdatedAt(new \DateTime());
        $em->persist($chat);
        $em->flush();
        //mercure
        // $update = new Update(
        //     $_ENV['MERCURE_URL'] . '/chatbox/' . $request->get('user'),
        //     json_encode([
        //         'texte' => $content->message,
        //         'type' => $content->type,
        //         'time' => new \DateTime()
        //     ]),//tru pour private
        // );
        //$hub->publish($update);

        //js
        //         const url = new URL('{{ TBgetenv('MERCURE_URL') }}');
        // url.searchParams.append('topic', '{{ TBgetenv('MERCURE_URL')~'/chatbox/'~ChatToken }}');
        // const eventSource = new EventSource(url);
        // eventSource.onmessage = e => console.log(e);

        return new JsonResponse(['message' => 'ok']);
    }
    #[route('/chatGetMessages/{user}', name: 'chatGetMessages', methods: ['GET'])]
    public function chatgetmessages(ChatRepository $chatRepository, Request $request)
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
    #[route('/admin/chatGet', name: 'chatGet', methods: ['GET'])]
    public function chatget(ChatRepository $chatRepository, Request $request)
    {
        $retour = [];
        $now = new DateTime('now');
        foreach ($chatRepository->findBy(['deletedAt' => null], ['id' => 'DESC']) as $chat) {
            $messages = [];
            foreach ($chat->getMessages() as $message) {
                $messages[] = [
                    'texte' => $message->getTexte(),
                    'created_in' =>  $message->getCreatedAt() ? $now->diff($message->getCreatedAt())->format('%djour %hh %imn %Ss') : '',
                    'type' => $message->getType()
                ];
            }
            $retour[] = [
                'user' => $chat->getUser(),
                'id' => $chat->getId(),
                'updated_in' => $chat->getUpdatedAt() ? $now->diff($chat->getUpdatedAt())->format('%d jour %hh %imn %Ss') : '',
                'messages' => array_reverse($messages)
            ];
        }
        return new JsonResponse($retour);
    }
    #[Route('/admin/chatboxs', name: 'chatboxs_index', methods: ['GET'])]
    public function chatboxs_index(ChatRepository $chatRepository, CsrfTokenManagerInterface $csrfTokenManagerInterface): Response
    {
        //ajout des csrfs
        $csrf = [];
        foreach ($chatRepository->findBy(['deletedAt' => null]) as $chat) {
            $csrf[$chat->getId()] = $csrfTokenManagerInterface->getToken('delete' . $chat->getId())->getValue();
        }
        return $this->render('/base/chatbox_index.html.twig', [
            'chats' => $chatRepository->findBy(['deletedAt' => null]),
            'csrf' => json_encode($csrf),
            'template_cards' => file_get_contents('/app/templates/chat_template_cards.html.twig'),
            'template_card' => file_get_contents('/app/templates/chat_template_card.html.twig'),
            'template_reponse' => file_get_contents('/app/templates/chat_template_reponse.html.twig'),
            'template_question' => file_get_contents('/app/templates/chat_template_question.html.twig'),

        ]);
    }
    #[Route('testmail/{from}')]
    public function testmail(MailerInterface $mailer, string $from)
    {
        $email = (new Email())
            ->from($from)
            ->to('michael@cadot.eu')
            ->cc('cc@example.com')
            //->bcc('bcc@example.com')
            //->replyTo('fabien@example.com')
            //->priority(Email::PRIORITY_HIGH)
            ->subject('Time for Symfony Mailer!')
            ->text('Sending emails is fun again!')
            ->html('<p>See Twig integration for better HTML integration!</p>');
        try {
            $mailer->send($email);
        } catch (TransportExceptionInterface $e) {
            throw new \Exception($e->getMessage());
        }
        return new JsonResponse('ok');
    }
}
