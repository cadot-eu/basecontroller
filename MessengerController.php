<?php

namespace App\Controller\base;

use App\Repository\base\MessagesRepository;
use Symfony\Component\HttpFoundation\RedirectResponse;
use App\Controller\base\ToolsController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\Transport\TransportInterface;

#[Route('/admin/messenger')]
class MessengerController extends ToolsController
{


    #[Route('/supprimer-toutes-les-taches-failed', name: 'supprimer_all_failed')]
    public function supprimer_all_failed(Request $request, MessagesRepository $message)
    {
        $message->deleteAllFailedMessages();
        $this->addFlash('success', 'Toutes les tâches échouées ont été effacées');
        return $this->redirectToRoute('admin_index');
    }
    #[Route('/supprimer-une-tache-failed/{id}', name: 'supprimer_failed')]
    public function supprimer_failed(Request $request, MessagesRepository $message, int $id)
    {
        if ($message->deleteFailedMessage($id))
            $this->addFlash('success', 'Tâche échouée effacée');
        else
            $this->addFlash('error', 'Impossible de supprimer cette tâche');
        return $this->redirectToRoute('admin_index');
    }
    #[Route('/reessayer-une-tache-failed/{id}', name: 'reessayer_failed')]
    public function reessayer_failed(Request $request, MessagesRepository $message, int $id)
    {
        if ($message->retryFailedMessage($id))
            $this->addFlash('success', 'Tâche échouée remis en file d\'attente');
        else
            $this->addFlash('error', 'Impossible de remettre cette tâche en file d\'attente');
        return $this->redirectToRoute('admin_index');
    }
    #[Route('/supprimer/tache/{id}', name: 'supprimer_task')]
    public function supprimer_task(Request $request, MessagesRepository $message, int $id)
    {
        if ($message->deleteMessage($id))
            $this->addFlash('success', 'Tâche effacée');
        else
            $this->addFlash('error', 'Impossible de supprimer cette tâche');
        return $this->redirectToRoute('admin_index');
    }
    #[Route('/supprimer/toutes-les-taches', name: 'supprimer_all_task')]
    public function supprimer_all_task(Request $request, MessagesRepository $message)
    {
        if ($message->deleteAllMessages())
            $this->addFlash('success', 'Toutes les tâches sont effacées');
        else
            $this->addFlash('error', 'Impossible de supprimer toutes les tâches');
        return $this->redirectToRoute('admin_index');
    }
}
