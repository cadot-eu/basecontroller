<?php

namespace App\Controller\base;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Stripe;

class StripeController extends AbstractController
{
    #[Route('/superadmin/stripe', name: 'app_stripe')]
    public function index(): Response
    {
        return $this->render('/base/stripe.html.twig');
    }


    #[Route('/superadmin/stripe/create-charge', name: 'app_stripe_charge', methods: ['POST'])]
    public function createCharge(Request $request)
    {
        Stripe\Stripe::setApiKey($_ENV["STRIPE_SECRET"]);
        Stripe\Charge::create([
            "amount" => 5 * 100,
            "currency" => "usd",
            "source" => $request->request->get('stripeToken'),
            "description" => "Binaryboxtuts Payment Test"
        ]);
        $this->addFlash(
            'success',
            'Payment Successful!'
        );
        return $this->redirectToRoute('app_stripe', [], Response::HTTP_SEE_OTHER);
    }
}
