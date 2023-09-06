<?php

namespace App\Controller\base;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use DateTime;


class ToolsentityController extends AbstractController
{
    public function toutSupprimer($entityclass, Request $request, EntityManagerInterface $em, $route = null, $flash = true)
    {
        $nomentity = $this->getEntityClassName($entityclass);
        foreach ($em->getRepository('App\\Entity\\' . \ucfirst($nomentity))->findAll() as $item) {
            if ($this->isCsrfTokenValid('delete_Devi', $request->request->get('_alltoken')) and $item->getDeletedAt() != null) {
                $em->remove($item);
            }
        }
        $em->flush();
        if ($flash) $this->addFlash('success', 'La corbeille est vidée');
        if ($route != null)
            return $this->redirectToRoute($route, [], Response::HTTP_SEE_OTHER);
        return $this->redirectToRoute($nomentity . "_deleted", [], Response::HTTP_SEE_OTHER);
    }
    public function supprimer($entityclass, $id, Request $request, EntityManagerInterface $em, $route = null, $flash = true)
    {
        $entity = $this->getEntityObject($entityclass, $em, $id);
        $nomentity = $this->getEntityClassName($entityclass);
        if ($this->isCsrfTokenValid('delete' . $entity->getId(), $request->request->get('_token'))) {
            if ($request->request->has('delete_delete')) {
                $em->remove($entity);
                if ($flash) $this->addFlash('success', "$nomentity supprimé");
            } else if ($request->request->has('delete_restore')) {
                if ($flash) $this->addFlash('success', "$nomentity restauré");
                $entity->setDeletedAt(null);
            } else {
                if ($flash) $this->addFlash('success', "$nomentity mis à la corbeille");
                $entity->setDeletedAt(new DateTime('now'));
            }
            $em->flush();
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


    private function getEntityObject($entityclass, $em, $id)
    {
        if (is_object($entityclass)) {
            return $entityclass;
        } else {
            return $em->getRepository($entityclass)->find($id);
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
}
