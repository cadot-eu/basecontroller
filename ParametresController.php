<?php

namespace  App\Controller\base;

use DateTime;
use App\Entity\base\Parametres;
use App\Form\base\ParametresType;
use App\Repository\base\ParametresRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use App\Service\base\FileUploader;
use Knp\Component\Pager\PaginatorInterface;

#[Route('/admin/parametres')]
class ParametresController extends AbstractController
{
    protected $em;
    public function __construct(
        EntityManagerInterface $em
    ) {
        $this->em = $em;
    }
    /* -------------------------------------------------------------------------- */
    /*                                    INDEX                                   */
    /* -------------------------------------------------------------------------- */
    #[Route('/', name: 'parametres_index', methods: ['GET'])]
    public function index(ParametresRepository $parametresRepository, Request $request, PaginatorInterface $paginator): Response
    {
        $dql = $parametresRepository->index($request->query->get('filterValue', ''), ['id', 'nom', 'valeur'], $request->query->get('sort'), $request->query->get('direction'), false);
        return $this->render('/parametres/index.html.twig', [
            'pagination' => $paginator->paginate($dql, $request->query->getInt('page', 1))
        ]);
    }
    /* -------------------------------------------------------------------------- */
    /*                                   DELETED                                  */
    /* -------------------------------------------------------------------------- */
    #[Route('/deleted', name: 'parametres_deleted', methods: ['GET'])]
    public function deleted(ParametresRepository $parametresRepository, Request $request, PaginatorInterface $paginator): Response
    {
        $dql = $parametresRepository->index($request->query->get('filterValue', ''), ['id', 'nom', 'valeur'], $request->query->get('sort', 'a.id'), $request->query->get('direction'), true);
        return $this->render('/parametres/index.html.twig', [
            'pagination' => $paginator->paginate($dql, $request->query->getInt('page', 1), 8)
        ]);
    }
    /* -------------------------------------------------------------------------- */
    /*                                    CHAMP                                    */
    /* -------------------------------------------------------------------------- */
    /**
     * @Route("/champ/{id}/{type}/{valeur}", name="parametres_champ", methods={"GET"})
     */
    public function champ(Parametres $parametres, $type = null, $valeur = null): Response
    {
        if ($type) {
            $method = 'set' . $type;
            $parametres->$method($valeur);
            $this->em->persist($parametres);
            $this->em->flush();
        }
        return $this->redirectToRoute('parametres_index', [], Response::HTTP_SEE_OTHER);
    }
    /* -------------------------------------------------------------------------- */
    /*                                NEW AND EDIT                                */
    /* -------------------------------------------------------------------------- */
    #[Route('/new', name: 'parametres_new', methods: ['GET', 'POST'])]
    #[Route('/{id}/edit', name: 'parametres_edit', methods: ['GET', 'POST'])]
    public function new(Request $request, FileUploader $fileUploader, Parametres $parametres = null, EntityManagerInterface $em): Response
    {
        if (!$parametres) $parametres = new Parametres(); //for new
        $form = $this->createForm(ParametresType::class, $parametres, ['username' => $this->getUser()->getEmail(),]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if ($request->files->get('parametres'))
                foreach ($request->files->get('parametres') as $name => $data) {
                    $fichier = $form->get($name)->getData();
                    if ($fichier) {
                        if (get_class($fichier) == 'Doctrine\Common\Collections\ArrayCollection' || get_class($fichier) == "Doctrine\ORM\PersistentCollection") {
                            $fichierName = [];
                            foreach ($fichier as $num => $fiche) {
                                if ($data[$num][key($data[$num])] != null) {
                                    $class = explode('\\', get_class($fiche));
                                    $fichierName = $fileUploader->upload($data[$num][key($data[$num])], "parametres/$name/" . key($data[$num]),);
                                    $functionE = 'set' . ucfirst(key($data[$num]));
                                    $fiche->$functionE($fichierName);
                                    $function = 'add' . end($class);
                                    $parametres->$function($fiche);
                                }
                            }
                        } else {
                            $fichierName = $fileUploader->upload($fichier, "parametres/$name",);
                            $function = 'set' . $name;
                            $parametres->$function($fichierName);
                        }
                    }
                    //delete value
                    else {
                        if ($request->get("parametres_" . $name) == 'à retirer') {
                            $function = 'set' . $name;
                            $parametres->$function('');
                        }
                    }
                }
            //TODO: par listener

            $em->persist($parametres);
            $em->flush();
            return $this->redirectToRoute('parametres_index', [], Response::HTTP_SEE_OTHER);
        }
        return $this->render('/parametres/new.html.twig', [
            'parametres' => $parametres,
            'form' => $form->createView()
        ]);
    }
    /* -------------------------------------------------------------------------- */
    /*                                    SHOW                                    */
    /* -------------------------------------------------------------------------- */
    #[Route('/{id}', name: 'parametres_show', methods: ['GET'])]
    public function show(Parametres $parametres): Response
    {
    }
    /* -------------------------------------------------------------------------- */
    /*                                    CLONE                                   */
    /* -------------------------------------------------------------------------- */
    #[Route('/{id}/clone', name: 'parametres_clone', methods: ['GET', 'POST'])]
    public function clone(Parametres $parametresc, EntityManagerInterface $em): Response
    {
        $parametres = clone $parametresc;
        if (property_exists($parametres, 'slug')) {
            $parametres->setslug($parametresc->getslug() . uniqid());
        }
        $parametres->setCreatedAt(new DateTime('now'));
        $em->persist($parametres);
        $em->flush();
        return $this->redirectToRoute('parametres_index', [], Response::HTTP_SEE_OTHER);
    }
    /* -------------------------------------------------------------------------- */
    /*                                   DELETE                                   */
    /* -------------------------------------------------------------------------- */
    #[Route('/{id}', name: 'parametres_delete', methods: ['POST'])]
    public function delete(Request $request, Parametres $parametres, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete' . $parametres->getId(), $request->request->get('_token'))) {
            if ($request->request->has('delete_delete')) {
                $em->remove($parametres);
            }
            if ($request->request->has('delete_restore'))
                $parametres->setDeletedAt(null);
            if ($request->request->has('delete_softdelete'))
                $parametres->setDeletedAt(new DateTime('now'));
            $em->flush();
        }
        if ($request->request->has('delete_softdelete'))
            return $this->redirectToRoute('parametres_index', [], Response::HTTP_SEE_OTHER);
        else
            return $this->redirectToRoute('parametres_deleted', [], Response::HTTP_SEE_OTHER);
    }
}
