<?php

namespace App\Controller\Client;

use App\Classe\Mailjet;
use Stripe\Stripe;
use App\Entity\Devis;
use App\Entity\Paiement;
use App\Entity\Vehicule;
use App\Entity\Reservation;
use App\Service\DateHelper;
use App\Entity\ModePaiement;
use Stripe\Checkout\Session;
use App\Service\TarifsHelper;
use App\Classe\ReservationClient;
use App\Classe\ReserverDevis;
use App\Classe\ValidationReservationClientSession;
use App\Controller\DevisController;
use App\Repository\DevisRepository;
use App\Repository\OptionsRepository;
use App\Repository\GarantieRepository;
use App\Repository\VehiculeRepository;
use App\Controller\ReservationController;
use App\Entity\AppelPaiement;
use App\Repository\ModePaiementRepository;
use App\Repository\ModeReservationRepository;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use MercurySeries\FlashyBundle\FlashyNotifier;
use phpDocumentor\Reflection\Types\This;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use function Composer\Autoload\includeFile;

class PaiementController extends AbstractController
{
    private $devisController;
    private $reservRepo;
    private $reservController;
    private $garantiesRepo;
    private $optionsRepo;
    private $dateHelper;
    private $tarifsHelper;
    private $vehiculeRepo;
    private $devisRepo;
    private $flashy;
    private $em;
    private $modePaiementRepo;
    private $validationSession;
    private $mail;
    private $modeReservationRepo;
    private $reserverDevis;


    public function __construct(

        ModeReservationRepository $modeReservationRepo,
        DevisController $devisController,
        ReservationRepository $reservRepo,
        ReservationController $reservController,
        OptionsRepository $optionsRepo,
        GarantieRepository $garantiesRepo,
        DateHelper $dateHelper,
        TarifsHelper $tarifsHelper,
        VehiculeRepository $vehiculeRepo,
        DevisRepository $devisRepo,
        FlashyNotifier $flashy,
        EntityManagerInterface $em,
        ModePaiementRepository $modePaiementRepo,
        ValidationReservationClientSession $validationSession,
        Mailjet $mail,
        ReserverDevis $reserverDevis

    ) {
        $this->devisController = $devisController;
        $this->reservRepo = $reservRepo;
        $this->reservController = $reservController;
        $this->garantiesRepo = $garantiesRepo;
        $this->optionsRepo = $optionsRepo;
        $this->dateHelper = $dateHelper;
        $this->tarifsHelper = $tarifsHelper;
        $this->vehiculeRepo = $vehiculeRepo;
        $this->devisRepo = $devisRepo;
        $this->flashy = $flashy;
        $this->em = $em;
        $this->modePaiementRepo = $modePaiementRepo;
        $this->validationSession = $validationSession;
        $this->mail = $mail;
        $this->modeReservationRepo = $modeReservationRepo;
        $this->reserverDevis = $reserverDevis;
    }

    /**
     * @Route("/espaceclient/paiement-stripe/echec/{stripeSessionId}", name="payement_fail", methods={"GET","POST"})
     */
    public function payementStripeFail(Request $request, $stripeSessionId)
    {
        $devis = $this->devisRepo->findOneBy(['stripeSessionId' => $stripeSessionId]);
        return $this->render('client/paiement/fail.html.twig', [
            'devis' => $devis
        ]);
    }

    /**
     * @Route("/espaceclient/paiement-stripe/succes/{stripeSessionId}", name="payement_success", methods={"GET","POST"})
     */
    public function payementStripeSuccess(Request $request, $stripeSessionId)
    {
        $devis = $this->devisRepo->findOneBy(['stripeSessionId' => $stripeSessionId]);

        //securité en cas de session non valide
        if (!$devis || $devis->getClient() != $this->getUser()) {
            $this->flashy->error("Le devis n'existe pas");
            return $this->redirectToRoute('espaceClient_index');
        }

        //indiquer dans la base de données que le devis a été reservé
        if (!$devis->getTransformed()) {
            $devis->setTransformed(true);
            $this->em->flush();
        }

        //enregistrement devis comme une réservation
        $this->transformerDevis->reserver($devis, $stripeSessionId);

        //enregistrer paiement dans table paiement
        $paiement = new Paiement;

        //sommepaiement en fonction de mode paiement choisis par le client
        $modePaiement = $devis->getPayementPercentage();
        if ($modePaiement == 25) {
            $sommePaiement = $this->tarifsHelper->VingtCinqPourcent($devis->getPrix());
        }
        if ($modePaiement == 50) {
            $sommePaiement = $this->tarifsHelper->CinquantePourcent($devis->getPrix());
        }
        if ($modePaiement == 100) {
            $sommePaiement = $devis->getPrix();
        }
        $paiement->setMontant($sommePaiement);
        $paiement->setReservation($this->reservRepo->findOneBy(['stripeSessionId' => $stripeSessionId]));
        $paiement->setStripeSessionId($stripeSessionId);
        $paiement->setDatePaiement($this->dateHelper->dateNow());
        $paiement->setClient($this->reservRepo->findOneBy(['stripeSessionId' => $stripeSessionId])->getClient());
        $paiement->setMotif("Réservation");
        $paiement->setDatePaiement($this->dateHelper->dateNow());
        $paiement->setModePaiement($this->modePaiementRepo->findOneBy(['libelle' => 'Virement']));
        $paiement->setCreatedAt($this->dateHelper->dateNow());
        $this->em->persist($paiement);
        $this->em->flush();

        //recupération entity reservation concerné
        $reservation = $this->reservRepo->findOneBy(['stripeSessionId' => $stripeSessionId]);
        //envoi de mail client pour confirmation de paiement
        $contentMail = 'Bonjour, votre réservation numéro ' . $reservation->getReference() . 'a bien été payé';
        //        $this->mail->send($reservation->getClient()->getMail(), $reservation->getClient()->getNom(), "Confirmation payement", $contentMail);

        $this->mail->confirmationPaiement(
            $reservation->getClient()->getNom(),
            $reservation->getClient()->getMail(),
            'Confirmation de paiement',
            $reservation->getDateReservation()->format('d/m/Y'),
            $reservation->getReference(),
            $reservation->getVehicule()->getMarque() . " " . $reservation->getVehicule()->getModele(),
            $reservation->getDateDebut()->format('d/m/Y H:i'),
            $reservation->getDateFin()->format('d/m/Y H:i'),
            $reservation->getPrix(),
            $sommePaiement
        );

        //ajouter dans appel à paiement si somme paiement inférieur à due
        if ($sommePaiement < $devis->getPrix()) {
            $appel = new AppelPaiement();
            $appel->setReservation($reservation);
            $appel->setPayed(false);
            $appel->setMontant($sommePaiement);
        }

        //vider session validation paiement 
        $this->validationSession->removeValidationSession();
        return $this->render('client/paiement/success.html.twig', [
            "reservation" => $reservation,
        ]);
    }
    //test de paiement par stripe (page de paiement hebergé sur site stripe)

    /**
     * @Route("/espaceclient/paiement-stripe/{refDevis}", name="paiementStripe", methods={"GET","POST"})
     */
    public function paiementStripe(Request $request, $refDevis)
    {
        $devis = $this->devisRepo->findOneBy(['numero' => $refDevis]);

        if (!$devis || $devis->getClient() != $this->getUser()) {
            $this->flashy->error("Le devis n'existe pas");
            return $this->redirectToRoute('espaceClient_index');
        }

        $modePaiement = $devis->getPayementPercentage();

        //sommepaiement en fonction de mode paiement choisis par le client
        if ($modePaiement == 25) {
            $sommePaiement = $this->tarifsHelper->VingtCinqPourcent($devis->getPrix());
        }
        if ($modePaiement == 50) {
            $sommePaiement = $this->tarifsHelper->CinquantePourcent($devis->getPrix());
        }
        if ($modePaiement == 100) {
            $sommePaiement = $devis->getPrix();
        }

        //key stripe à changer si changement de compte stripê
        //key for test
        //        Stripe::setApiKey('sk_test_51JiGijGsAu4Sp9QQtyfjOoOQMb6kfGjE1z50X5vrW6nS7wLtK5y2HmodT3ByrI7tQl9dsvP69fkN4vVfH5676nDo00VgFOzXct');


        //key of Joel compte (mode test)
        Stripe::setApiKey('sk_test_51JQIYYBicYM5dT7NhQraQ8jd57aqJBIDuru7VpKTcmwvHIDO8pMgL4vj1ARZTFgdznDkDG9MKaQegs8xCThlvJA300LmatfyYq');
        $YOUR_DOMAIN = 'https://joellocation.com';
        $checkout_session = Session::create([
            'customer_email' => $devis->getClient()->getMail(),
            'payment_method_types' => ['card'],
            'line_items' => [[
                //pour plusieur produits, ajouter un autre tableau price_data
                'price_data' => [
                    'currency' => 'eur',
                    'product_data' => [
                        'name' => 'Réservation du ' . $devis->getDateDepart()->format('d/m/Y H:i') . " au " . $devis->getDateRetour()->format('d/m/Y H:i'),
                        'images' => [$YOUR_DOMAIN . "/uploads/vehicules" . $devis->getVehicule()->getImage()],
                        'description' => $devis->getVehicule()->getMarque() . " " . $devis->getVehicule()->getModele()
                    ],
                    'unit_amount' => $sommePaiement * 100
                ],
                'quantity' => 1,
            ]],
            'mode' => 'payment',
            'success_url' => $YOUR_DOMAIN . '/espaceclient/paiement-stripe/succes/{CHECKOUT_SESSION_ID}',
            'cancel_url' => $YOUR_DOMAIN . '/espaceclient/paiement-stripe/echec/{CHECKOUT_SESSION_ID}',
        ]);

        //enregistrer en base de données sessionStripe, utile pour recuperer le devis plus tard
        $devis->setStripeSessionId($checkout_session->id);
        $this->em->flush();

        // rediriger vers page de paiement hebergé sur stripe
        return $this->redirect($checkout_session->url);
    }
}
