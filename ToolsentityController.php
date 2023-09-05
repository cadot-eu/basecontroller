<?php

namespace App\Controller\base;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use DateTime;


class ToolsentityController extends AbstractController
{

    public function supprimer($entityclass, $id, Request $request, EntityManagerInterface $em, $route = null)
    {

        if (preg_match('/\\\\([\w]+)$/', $entityclass, $matches)) {
            $entity = $em->getRepository($entityclass)->find($id);
            $nomentity = $matches[1];
        } else {
            $entity = $entityclass;
            $nomentity = $entityclass;
        }
        if ($this->isCsrfTokenValid('delete' . $entity->getId(), $request->request->get('_token'))) {
            if ($request->request->has('delete_delete')) {
                $em->remove($entity);
                $this->addFlash('success', "$nomentity supprimé");
            } else if ($request->request->has('delete_restore')) {
                $this->addFlash('success', "$nomentity restauré");
                $entity->setDeletedAt(null);
            } else {
                $this->addFlash('success', "$nomentity mis à la corbeille");
                $entity->setDeletedAt(new DateTime('now'));
            }
            $em->flush();
        } else {
            $this->addFlash('danger', "Erreur de token");
        }
        if ($route != null)
            return $this->redirectToRoute($route, [], Response::HTTP_SEE_OTHER);
        if ($request->request->has('delete_softdelete'))
            return $this->redirectToRoute("$nomentity_index", [], Response::HTTP_SEE_OTHER);
        else
            return $this->redirectToRoute("$nomentity_deleted", [], Response::HTTP_SEE_OTHER);
    }
}
