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
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Serializer\SerializerInterface;
use DateTime;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use PHPUnit\Extensions\Selenium2TestCase\URL;
use Psy\Readline\Hoa\EventSource;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Process\Process;
use Stripe\Stripe;
use Stripe\Checkout\Session;
use Dompdf\Adapter\GD;
use Symfony\Component\Notifier\Message\SmsMessage;
use App\Entity\User;
use App\Service\base\ToolsHelper;

class ToolsController extends AbstractController
{
    //implémentation pour tous les controllers
    //RegistrationController comme base ;-)
    private EmailVerifier $emailVerifier;

    protected $logger, $translator, $em, $mailer, $fileUploader, $toolsentityController;

    public function __construct(EmailVerifier $emailVerifier, LoggerInterface $logger, TranslatorInterface $translator, EntityManagerInterface $em, MailerInterface $mailer, FileUploader $fileUploader, ToolsentityController $toolsentityController)
    {
        $this->emailVerifier = $emailVerifier;
        $this->logger = $logger;
        $this->translator = $translator;
        $this->em = $em;
        $this->mailer = $mailer;
        $this->fileUploader = $fileUploader;
        $this->toolsentityController = $toolsentityController;
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
        $filename = $fileUploader->upload(
            $request->files->get('file-0'),
            $name . '/',
            $filter
        );

        // $widths = [32, 128, 300, 600, 1080, 1920];
        // foreach ($widths as $width) {
        //  $temp = $filterService->getUrlOfFilteredImage($filename, $width);
        //  $destDir[$width] = str_replace('http://', 'https://', $temp);
        // }
        return new Response(
            '{"result": [
{
"url": "/' .
                $filename .
                '",
"name": "test_image.jpg",
"size": "561276"
}
]}'
        );
    }



    /* ------------ permet de sélectionner dans une entité un élément ----------- */
    /* ------ et d'avoir une url coper dans le presse papier par sélection ------ */
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
    #[
        Route(
            '/admin/SelectAndCopy/{entitie}/{champs}/{recherche}/{affichage}/{copy}/{limit}',
            name: 'selectandcopy'
        )
    ]
    public function SAndCopy(EntityManagerInterface $em, $entitie, $champs, $recherche, $affichage, $copy, $limit)
    {
        //recherche dans les titres
        $query = $em->createQuery('SELECT p FROM App\Entity\\' . ucfirst($entitie) . ' p WHERE p.' . $champs . ' LIKE :recherche')->setParameter('recherche', '%' . $recherche . '%');
        $entities = array_slice($query->getResult(), 0, $limit);
        $tablo = [];
        $getaffichage = 'get' . ucwords($affichage);
        $getcopy = 'get' . ucwords($copy);
        foreach ($entities as $entity) {
            $tablo[] = ['affichage' => $entity->$getaffichage(), 'copy' => $entity->$getcopy(),];
        }
        return new JsonResponse($tablo);
    }

    /* -------------------------------------------------------------------------- */
    /*                testeur de liens et création du fichier json                */
    /* -------------------------------------------------------------------------- */
    #[Route('/superadmin/linktester', name: 'linktester')]
    public function linktester()
    {
        $file = '';
        if (file_exists('/app/tests/linktests.json')) {
            $file = file_get_contents('/app/tests/linktests.json');
        }
        return $this->render('base/test_links.html.twig', [
            'links' => $file
        ]);
    }
    /* -------------------------------------------------------------------------- */
    /*           Sert pour les indexs pour changer l'ordre des éléments           */
    /* -------------------------------------------------------------------------- */
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
    #[route('/admin/changeordre/{entity}/{id}/{action}', name: 'change_ordre', methods: ['GET'])]
    public function changeOrdre(EntityManagerInterface $em, string $entity, int $id, string $action): Response
    {
        $faqs = $em->getRepository('App\\Entity\\' . ucwords($entity))->findBy(['deletedAt' => null], ['ordre' => 'ASC']);
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
        return $this->redirectToRoute(
            strtolower($entity) . '_index',
            ['sort' => 'a.ordre', 'direction' => 'asc'],
            Response::HTTP_SEE_OTHER
        );
    }

    #[Route('testmail/{from}')]
    public function testmail(MailerInterface $mailer, string $from)
    {
        $email = (new Email())
            ->from($from)
            ->to('michael@cadot.eu')
            //->bcc('bcc@example.com')
            //->replyTo('fabien@example.com')
            //->priority(Email::PRIORITY_HIGH)
            ->subject('Email test')
            ->text('Sending emails is fun again!')
            ->html('<p>See Twig integration for better HTML integration!</p>');
        try {
            $mailer->send($email);
            $this->addFlash('success', 'Votre message a bien été envoyé');
        } catch (TransportExceptionInterface $e) {
            $this->addFlash('error', 'Une erreur est survenue lors de l\'envoi du message, l\'administrateur a été prévenu et votre message sera traité dans les plus brefs délais');
            captureMessage('Envoie mail: ' . $e, new Severity('error'), new EventHint(['tags' => ['resolver' => 'mick']]));
            throw new \Exception($e);
        }
        return new JsonResponse('ok');
    }
    #[Route('/admin/getLiipFilters', name: 'getLiipFilters', methods: ['GET'])]
    public function getLiipFilters(FilterService $filterService): Response
    {
        $filters = [];
        foreach (array_keys($this->getParameter('liip_imagine.filter_sets'))
            as $filter) {
            $filters[] = $filter;
        }
        return new JsonResponse($filters);
    }
    #[Route('/tools/getPngForTemplate/{liipfilter}', name: 'getLiipFilters', methods: ['GET'])]
    public function createPngForTemplate(
        FilterService $filterService,
        string $liipfilter
    ): Response {
        header("Content-Type: image/png"); //change the php file to an image
        $image = @imagecreate(300, 300)
            or die("Cannot Initialize new GD image stream");
        $background = imagecolorallocate($image, 200, 200, 200);
        $font_size = 20;
        $angle = 0;
        $font = '/app/assets/public/arial.ttf';
        list($left, $bottom, $right,,, $top) = imageftbbox($font_size, $angle, $font, $liipfilter);
        // Determine offset of text
        $left_offset = ($right - $left) / 2;
        $top_offset = ($bottom - $top) / 2;
        // Generate coordinates
        $x = 150 - $left_offset;
        $y = 150 + $top_offset;
        // Add text to image
        imagettftext($image, $font_size, $angle, $x, $y, imagecolorallocate($image, 0, 0, 0), $font, $liipfilter);
        imagepng($image, '/app/public/pngtemplate.png');

        return new Response(
            file_get_contents($filterService->getUrlOfFilteredImage('/pngtemplate.png', $liipfilter)),
            Response::HTTP_OK,
            ['content-type' => 'image/png']
        );
    }


    public function generateSitemaps(EntityManagerInterface $em, array $repositories, $request)
    {
        $baseurl = $request->getSchemeAndHttpHost() . '/';
        $urls = [];
        foreach ($repositories as $repository) {
            $objetEntity = 'App\Entity\\' . ucfirst($repository);
            $reflexion = new \ReflectionClass(new $objetEntity());
            $etat = $reflexion->hasProperty('etat') ? ['etat' => 'en ligne'] : [];
            $posts = $em->getRepository('App\\Entity\\' . ucwords($repository))->findBy(array_merge(['deletedAt' => null], $etat), ['updatedAt' => 'DESC']);
            foreach ($posts as $post) {
                $url = ['loc' => $baseurl . "les-" . $repository . "s/" . $post->getSlug()];
                if ($repository == 'categorie') {
                    $url = ['loc' => $baseurl . "article/categorie/" . $post->getSlug()];
                }
                if ($post->getUpdatedAt() !== null) {
                    $url['lastmod'] = $post->getUpdatedAt()->format('Y-m-d');
                }
                $urls[] = $url;
            }
        }
        $response = new Response(
            $this->renderView('/sitemap.html.twig', ['urls' => $urls]),
            200
        );
        $response->headers->set('Content-Type', 'text/xml');

        return $response;
    }



    //function pour tester la vaidité d'un siret par le site de l'insee, on prend le bearer dans $_ENV['INSEE_TOKEN']
    #[Route('/admin/siret/{siret}', name: 'veriffunction_siret', methods: ['GET'])]
    public function siret($siret): Response
    {
        //création du token https://api.insee.fr/token si pas de token en cookies
        if (!isset($_COOKIE['tokeninsee'])) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'https://api.insee.fr/token');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            //on ajoute grant_type=client_credentials
            curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
            //on ajoute le client_id et le client_secret
            \curl_setopt($ch, CURLOPT_USERPWD, $_ENV['INSEE_CLIENT_ID'] . ':' . $_ENV['INSEE_CLIENT_SECRET']);
            //on ajoute le content type
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/x-www-form-urlencoded',
            ]);
            //on exécute la requête
            $output = curl_exec($ch);
            //on ferme la session cURL
            curl_close($ch);
            //on récupère le token
            $token = json_decode($output)->access_token;
            //on met le token en cookies
            setcookie('tokeninsee', $token, time() + 3600, '/');
        }

        // URL du site Web de la vérification SIRET
        $url = 'https://api.insee.fr/entreprises/sirene/V3/siret/' . $siret;

        // Création d'un objet cURL
        $ch = curl_init();
        // Configuration des options
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $_COOKIE['tokeninsee'],
        ]);
        // Exécution de la requête et on retourne le résultat
        $output = curl_exec($ch);
        // Fermeture de la session cURL
        curl_close($ch);
        //on retourne le résultat

        return new Response($output);
    }
    //function pour envoyer un mail
    public function sendmail($to, $subject, $body, $message = true)
    {
        if ($to = 'admin')
            $to = $_ENV['MAILER_SENDER'];
        $email = (new Email())
            ->to($to)
            ->subject($subject)
            ->text($body);
        try {
            $this->mailer->send($email);
            if ($message)
                $this->addFlash('success', 'Votre message a bien été envoyé');
        } catch (TransportExceptionInterface $e) {
            $this->addFlash('error', 'Une erreur est survenue lors de l\'envoi du message, l\'administrateur a été prévenu et votre message sera traité dans les plus brefs délais');
            captureMessage('Envoie mail: ' . $e, new Severity('error'), new EventHint(['tags' => ['resolver' => 'mick']]));
            throw new \Exception($e);
        }
    }
    public function sendsms($to, $message)
    {
        $options = (new ProviderOptions())
            ->setPriority('high');

        $sms = new SmsMessage(
            // the phone number to send the SMS message to
            $to,
            // the message
            $message,
            // you can also add options object implementing MessageOptionsInterface
            $options
        );

        return $texter->send($sms);
    }
    public function notifier(User $user, $message = '', $subject = ''): Response
    {
        if ($message) {
            if ($subject == '') $subjetc = 'Information de ' . explode('<', _ENV['MAILER_FROM'])[0];
            //on regarde les paramètres de notification de l'utilisateur
            $params = $user->getParametres()['Communication_principale']['valeur'];
            $reponse = [];
            foreach ($params as $param) {
                switch ($param) {
                    case 'sms':
                        $reponse[] = $this->sendsms($user->getTelephone(), $message);
                        break;
                    case 'email':
                        $reponse[] = $this->sendmail($user->getEmail(), $subject, $message, false);
                        break;
                }
            }
        }
        return new JsonResponse($reponse);
    }

    /**
     * Retrieves the statistics for each entity in the given list.
     *
     * @param array $liste The list of entities and their corresponding queries. exemple: ['bien' => ['etat' => ['en ligne', 'brouillon']], 'user' => ['situation' => ['actif', 'inactif']]];
   
     * @return array The statistics for each entity.
     */
    public function Etats_Repository(array $liste, EntityManagerInterface $em): array
    {
        // Initialize the statistics array
        $stats = [];

        // Iterate through each entity and its query
        foreach ($liste as $stat => $demande) {
            // Get the repository for the entity
            $Repository = $em->getRepository('App\Entity\\' . ucfirst($stat));

            // Initialize the temporary array for storing query results
            $tab = [];

            // Iterate through each query and its values
            foreach ($demande as $key => $values) {
                // Iterate through each value and count the number of entities satisfying the query
                foreach ($values as $value) {
                    $tab[$key][$value] = count($Repository->findBy([$key => $value]));
                }
            }

            // Store the query results in the statistics array
            $stats['data'][$stat] = $tab;
            $stats['liste'] = $liste;
        }

        // Return the statistics
        return $stats;
    }

    /**
     * The function "supprimer" is a route handler in a PHP application that deletes an entity based on
     * its ID and redirects to a specified route.
     * 
     * @param entity The "entity" parameter represents the name of the entity that you want to delete.
     * It is a string value.
     * @param id The "id" parameter represents the identifier of the entity that you want to delete. It
     * is used to specify which entity should be deleted from the database.
     * @param route The "route" parameter is an optional parameter that specifies the route to redirect
     * to after the entity is deleted. If a value is provided for this parameter, the user will be
     * redirected to the specified route after the deletion is completed. If no value is provided, the
     * user will not be redirected and the
     * @param Request request The `` parameter is an instance of the `Request` class, which
     * represents an HTTP request. It contains information about the request such as the request method,
     * headers, query parameters, and request body. It is used to retrieve data from the request and
     * pass it to the `supprimer`
     * 
     * @return Response a Response object.
     */
    #[Route('/admin/supprimer/{entity}/{id}/{route}', name: 'entity_delete', methods: ['POST'])]
    public function supprimer($entity, $id, Request $request, $route = null): Response
    {
        return $this->toolsentityController->supprimer($entity, $id,  $request, $this->em, $route);
    }
    #[Route('/admin/tout-supprimer/{entity}/{route}', name: 'entity_all_delete', methods: ['POST'])]
    public function toutSupprimer($entity, Request $request, $route = null): Response
    {
        return $this->toolsentityController->toutSupprimer($entity,  $request, $this->em, $route);
    }
}
