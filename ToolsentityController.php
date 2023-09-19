<?php

namespace App\Controller\base;

use App\Service\base\FileUploader;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use DateTime;
use App\Service\base\ToolsHelper;


class ToolsentityController extends AbstractController
{
    private $fileUploader, $em;
    public function __construct(FileUploader $fileUploader, EntityManagerInterface $em)
    {
        $this->fileUploader = $fileUploader;
        $this->em = $em;
    }
    public function toutSupprimer($entityclass, Request $request,  $route = null, $flash = true)
    {
        $nomentity = $this->getEntityClassName($entityclass);
        foreach ($this->em->getRepository('App\\Entity\\' . \ucfirst($nomentity))->findAll() as $item) {
            if ($this->isCsrfTokenValid('delete_Devi', $request->request->get('_alltoken')) and $item->getDeletedAt() != null) {
                $this->em->remove($item);
            }
        }
        $this->em->flush();
        if ($flash) $this->addFlash('success', 'La corbeille est vidée');
        if ($route != null)
            return $this->redirectToRoute($route, [], Response::HTTP_SEE_OTHER);
        return $this->redirectToRoute($nomentity . "_deleted", [], Response::HTTP_SEE_OTHER);
    }
    public function supprimer($entityclass, $id, Request $request, $route = null, $flash = true)
    {
        $entity = $this->getEntityObject($entityclass, $this->em, $id);
        $nomentity = $this->getEntityClassName($entityclass);
        if ($this->isCsrfTokenValid('delete' . $entity->getId(), $request->request->get('_token'))) {
            if ($request->request->has('delete_delete')) {
                $this->em->remove($entity);
                if ($flash) $this->addFlash('success', "$nomentity supprimé");
            } else if ($request->request->has('delete_restore')) {
                if ($flash) $this->addFlash('success', "$nomentity restauré");
                $entity->setDeletedAt(null);
            } else {
                if ($flash) $this->addFlash('success', "$nomentity mis à la corbeille");
                $entity->setDeletedAt(new DateTime('now'));
            }
            $this->em->flush();
        } else {
            if ($flash) $this->addFlash('danger', "Erreur de token");
        }
        if ($route != null)
            return $this->redirectToRoute($route, [], Response::HTTP_SEE_OTHER);
        if ($request->request->has('delete_softdelete'))
            return $this->redirectToRoute($nomentity . "_index", [], Response::HTTP_SEE_OTHER);
        else
            return $this->redirectToRoute($nomentity . "_deleted", [], Response::HTTP_SEE_OTHER);
    }
    public function voir($entity)
    {
        //on boucle sur les functions et on affiche les éléments
        $reflexion = new \ReflectionClass($entity);
        $name = \strtolower($reflexion->getShortName());
        $rows = [];
        foreach ($reflexion->getMethods() as $method) { //on créé un tableau avec le maximum d'information, nom, type, valeur
            //on de prend que les getters
            if (substr($method->getName(), 0, 3) != 'get')
                continue;
            //on vérifie que la propriété existe
            if (!property_exists($entity, lcfirst(substr($method->getName(), 3))))
                continue;
            $rows[] = [
                'nom' => substr($method->getName(), 3),
                'type' => $method->getReturnType(),
                'valeur' => $method->invoke($entity)
            ];
        }
        return $this->render("/$name/voir.html.twig", [
            'methods' => $rows,
            'entity' => $entity
        ]);
    }

    public function newedit($entity, Request $request, $route = null)
    {
        // Si $entity est une chaîne de caractères, on est en mode new
        $nomentity = \is_object($entity) ? $this->getEntityClassName($entity) : $entity;
        $entityClass = 'App\\Entity\\' . Ucfirst($nomentity);
        $entityType = 'App\\Form\\' . Ucfirst($nomentity) . 'Type';

        if (\is_object($entity) == false) $entity = new $entityClass(); //pour create
        $form = $this->createForm($entityType, $entity, []);
        if ($this->processFiles($form, $request, $entity)) {
            $this->em->persist($entity);
            $this->em->flush();
            $this->addFlash('success', "$nomentity " . $entity->getId() . " ajouté");
            if ($route)
                return $this->redirectToRoute($route, []);
            else
                return $this->redirectToRoute($nomentity . "_index", [], Response::HTTP_SEE_OTHER);
        }

        return $this->render("/" . $nomentity . "/new.html.twig", [
            $nomentity => $entity,
            'form' => $form->createView()
        ]);
    }

    public function champ($entity, $type, $valeur, $one)
    {
        $nomentity = $this->getEntityClassName($entity);
        $Repository = $this->em->getRepository('App\\Entity\\' . \ucfirst($nomentity));
        if ($one) {
            foreach ($Repository->findAll() as $objet) {
                $method = 'set' . $type;
                $objet->$method(false);
                $this->em->persist($objet);
            }
        }
        if ($type) {
            $method = 'set' . $type;
            $entity->$method($valeur);
            $this->em->persist($entity);
            $this->em->flush();
        }
        $this->addFlash('success', ucfirst($type) . " $nomentity " . $entity->getId() . " mis à " . $valeur);
        return $this->redirectToRoute($nomentity . '_index', [], Response::HTTP_SEE_OTHER);
    }

    public function clone($entityc)
    {
        $entity = clone $entityc;
        if (property_exists($entity, 'slug')) {
            $entity->setslug($entityc->getslug() . uniqid());
        }
        $entity = ToolsHelper::SetSlug($this->em, $entity);
        $this->em->persist($entity);
        $this->em->flush();
        return $this->redirectToRoute($entity . '_index', [], Response::HTTP_SEE_OTHER);
    }


    private function getEntityObject($entityclass, $id)
    {
        if (is_object($entityclass)) {
            return $entityclass;
        } else {
            return $this->em->getRepository($entityclass)->find($id);
        }
    }
    private function getEntityClassName($entityclass)
    {
        if (is_object($entityclass)) {
            $matches = [];
            preg_match('/\\\\([\w]+)$/', get_class($entityclass), $matches);
            return strtolower($matches[1]);
        } else {
            $matches = [];
            return strtolower($entityclass);
        }
    }
    public function processFiles($form, $request, &$objet)
    {
        $class = explode('\\', \get_class($objet));
        $entity = \strtolower($class[count($class) - 1]);
        //on suprime l'id de l'objet
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if ($request->files->get($entity)) {
                foreach ($request->files->get($entity) as $name => $data) {
                    $fichier = $form->get($name)->getData();
                    if ($fichier) {
                        if (get_class($fichier) == 'Doctrine\Common\Collections\ArrayCollection' || get_class($fichier) == "Doctrine\ORM\PersistentCollection") {
                            $fichierName = [];
                            foreach ($fichier as $num => $fiche) {
                                if ($data[$num][key($data[$num])] != null) {
                                    $class = explode('\\', get_class($fiche));
                                    $fichierName = $this->fileUploader->upload($data[$num][key($data[$num])], "$entity/$name/" . key($data[$num]),);
                                    $functionE = 'set' . ucfirst(key($data[$num]));
                                    $fiche->$functionE($fichierName);
                                    $function = 'add' . substr(ucfirst($name), 0, -1);
                                    $objet->$function($fiche);
                                }
                            }
                        } else {
                            $fichierName = $this->fileUploader->upload($fichier, "$entity/$name",);
                            $function = 'set' . $name;
                            $objet->$function($fichierName);
                        }
                    }
                    // Suppression de la valeur
                    else {
                        if ($request->get("$entity_" . $name) == 'à retirer') {
                            $function = 'set' . $name;
                            $objet->$function('');
                        }
                    }
                }
            }

            if (property_exists($objet, 'slug')) {
                $objet = ToolsHelper::SetSlug($this->em, $objet);
            }

            return true; // Le formulaire a été traité avec succès
        }

        return false; // Le formulaire n'a pas été traité avec succès
    }
}
