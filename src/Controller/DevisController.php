<?php

namespace App\Controller;

use Knp\Snappy\Pdf;
use App\Entity\Devis;
use App\Form\DevisType;
use App\Entity\User;
use App\Entity\Vehicule;
use App\Entity\Reservation;
use App\Repository\UserRepository;
use App\Form\DevisEditVehiculeType;
use App\Repository\DevisRepository;
use App\Repository\TarifsRepository;
use App\Repository\OptionsRepository;
use App\Repository\GarantieRepository;
use App\Repository\VehiculeRepository;
use App\Controller\ReservationController;
use App\Repository\ReservationRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Knp\Bundle\SnappyBundle\Snappy\Response\PdfResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
// Include Dompdf required namespaces
use Dompdf\Dompdf;
use Dompdf\Options;

class DevisController extends AbstractController
{

    private $reservationRepo;
    private $userRepo;
    private $vehiculeRepo;
    private $devisRepo;
    private $tarifsRepo;
    private $garantiesRepo;
    private $optionsRepo;
    private $reservController;


    public function __construct(UserRepository $userRepo, DevisRepository $devisRepo, ReservationRepository $reservationRepo, VehiculeRepository $vehiculeRepo,   TarifsRepository $tarifsRepo, OptionsRepository $optionsRepo, GarantieRepository $garantiesRepo, ReservationController $reservController)
    {

        $this->reservationRepo = $reservationRepo;
        $this->vehiculeRepo = $vehiculeRepo;
        $this->userRepo = $userRepo;
        $this->devisRepo = $devisRepo;
        $this->tarifsRepo = $tarifsRepo;
        $this->garantiesRepo = $garantiesRepo;
        $this->optionsRepo = $optionsRepo;
        $this->reservController = $reservController;
    }

    /**
     * @Route("/devis", name="devis_index")
     */
    public function index(): Response
    {
        $devis = $this->devisRepo->findAll();

        return $this->render('admin/devis/index.html.twig', [
            'devis' => $devis
        ]);
    }


    /**
     * @Route("/newDevis", name="devis_new", methods={"GET","POST"})
     */
    public function newDevis(Request $request): Response
    {
        $devis = new Devis();
        if ($request->isXmlHttpRequest()) {
            $idClient =  $request->query->get('idClient');
            $agenceDepart = $request->query->get('agenceDepart');
            $agenceRetour = $request->query->get('agenceRetour');
            $lieuSejour = $request->query->get('lieuSejour');
            $dateTimeDepart = $request->query->get('dateTimeDepart');
            $dateTimeRetour = $request->query->get('dateTimeRetour');
            $vehiculeIM = $request->query->get('vehiculeIM');
            $conducteur = $request->query->get('conducteur');
            $idSiege = $request->query->get('idSiege');
            $idGarantie = $request->query->get('idGarantie');

            $siege = $this->optionsRepo->find($idSiege);
            $garantie = $this->garantiesRepo->find($idGarantie);
            $vehicule = $this->vehiculeRepo->findByIM($vehiculeIM);
            $client = $this->userRepo->find($idClient);
            $duree = $this->calculDuree($dateTimeDepart, $dateTimeRetour);

            $devis->setVehicule($vehicule);
            $devis->setClient($client);
            $devis->setAgenceDepart($agenceDepart);
            $devis->setAgenceRetour($agenceRetour);
            $devis->setDateDepart(new \DateTime($dateTimeDepart));
            $devis->setDateRetour(new \DateTime($dateTimeRetour));
            $devis->setGarantie($garantie);
            $devis->setSiege($siege);
            $devis->setConducteur($conducteur);
            $devis->setLieuSejour($lieuSejour);
            $devis->setDuree($duree);
            $devis->setDateCreation(new \DateTime('NOW'));


            $prix = $this->calculTarif($dateTimeDepart, $dateTimeRetour, $siege, $garantie, $vehicule);

            $devis->setPrix($prix);

            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($devis);
            $entityManager->flush();

            return $this->redirectToRoute('devis_index');
        }

        $devis = $this->devisRepo->findAll();

        return $this->render('admin/devis/index.html.twig', [
            'devis' => $devis
        ]);
    }


    /**
     * @Route("devis/{id}", name="devis_show", methods={"GET"})
     */
    public function show(Devis $devis): Response
    {

        return $this->render('admin/devis/details.html.twig', [
            'devis' => $devis,
        ]);
    }


    /**
     * @Route("devis/{id}/edit", name="devis_edit", methods={"GET","POST"})
     */
    public function edit(Request $request, Devis $devis): Response
    {
        $form = $this->createForm(DevisType::class, $devis);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $dateDepart = $request->request->get('devis')['dateDepart'];
            $dateRetour = $request->request->get('devis')['dateRetour'];
            $siege = $request->request->get('devis')['siege'];
            $garantie = $request->request->get('devis')['garantie'];
            $vehicule = $request->request->get('devis')['vehicule'];
            // $vehicule = $request->request->get('selectVehicule');
            $prix = $this->calculTarif($dateDepart, $dateRetour, $siege, $garantie, $vehicule);
            $devis->setPrix($prix);
            // $devis->setVehicule($this->vehiculeRepo->find($vehicule));
            $devis->setDuree($this->calculDuree($dateDepart, $dateRetour));
            $this->getDoctrine()->getManager()->flush();
            return $this->redirectToRoute('devis_index');
        }

        return $this->render('admin/devis/edit.html.twig', [
            'form' => $form->createView(),
            'devis' => $devis
        ]);
    }


    /**
     * @Route("devis/{id}/editVehicule", name="devis_edit_vehicule", methods={"GET","POST"})
     */
    public function editVehicule(Request $request, Devis $devis): Response
    {
        $formVehicule = $this->createForm(DevisEditVehiculeType::class, $devis);

        $formVehicule->handleRequest($request);

        if ($formVehicule->isSubmitted() && $formVehicule->isValid()) {


            $dateDepart = $request->request->get('devis_edit_vehicule')['dateDepart'];
            $dateRetour = $request->request->get('devis_edit_vehicule')['dateRetour'];
            $vehicule = $request->request->get('selectVehicule');


            $prix = $this->calculTarif($dateDepart, $dateRetour, $devis->getSiege()->getPrix(), $devis->getGarantie()->getPrix(), $vehicule);
            $devis->setPrix($prix);
            $devis->setVehicule($this->vehiculeRepo->find($vehicule));
            $devis->setDuree($this->calculDuree($dateDepart, $dateRetour));
            $this->getDoctrine()->getManager()->flush();
            return $this->redirectToRoute('devis_index');
        }

        return $this->render('admin/devis/editVehicule.html.twig', [
            'formVehicule' => $formVehicule->createView(),
            'devis' => $devis
        ]);
    }

    /**
     * @Route("/{id}", name="devis_delete", methods={"DELETE"})
     */
    public function delete(Request $request, Devis $devis): Response
    {
        if ($this->isCsrfTokenValid('delete' . $devis->getId(), $request->request->get('_token'))) {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($devis);
            $entityManager->flush();
        }

        return $this->redirectToRoute('devis_index');
    }

    /**
     * @Route("devis/{id}/reserver", name="devis_reserver", methods={"GET","POST"})
     */
    public function reserver(Request $request, Devis $devis)
    {
        $reservation = new Reservation();
        $reservation->setVehicule($devis->getVehicule());
        $reservation->setClient($devis->getClient());
        $reservation->setDateDebut($devis->getDateDepart());
        $reservation->setDateFin($devis->getDateRetour());
        $reservation->setAgenceDepart($devis->getAgenceDepart());
        $reservation->setAgenceRetour($devis->getAgenceRetour());
        $reservation->setGarantie($devis->getGarantie());
        $reservation->setSiege($devis->getSiege());
        $reservation->setPrix($devis->getPrix());
        $reservation->setDateReservation(new \DateTime('NOW'));
        $reservation->setCodeReservation('devisTransformé');

        $entityManager = $this->reservController->getDoctrine()->getManager();
        $entityManager->persist($reservation);
        $entityManager->flush();
        // dump($reservation);
        // die();
        return $this->redirectToRoute('reservation_index');
    }

    function monthName($date)
    {
        if (is_object($date)) {
            $month = $date->format('m');
        } else {
            $month = (new \DateTime($date))->format('m');
        }
        $monthFR = null;
        switch ($month) {
            case "01":
                $monthFR = 'Janvier';
                break;
            case "02":
                $monthFR = 'Février';
                break;
            case "03":
                $monthFR = 'Mars';
                break;
            case "04":
                $monthFR = 'Avril';
                break;
            case "05":
                $monthFR = 'Mai';
                break;
            case "06":
                $monthFR = 'Juin';
                break;
            case "07":
                $monthFR = 'Juillet';
                break;
            case "08":
                $monthFR = 'Août';
                break;
            case "09":
                $monthFR = 'Septembre';
                break;
            case "10":
                $monthFR = 'Octobre';
                break;
            case "11":
                $monthFR = 'Novembre';
                break;
            case "12":
                $monthFR = 'Décembre';
                break;
        }

        return $monthFR;
    }

    function calculTarif($dateDepart, $dateRetour, $siege, $garantie, $vehicule)
    {

        if (!is_object($siege) && !is_object($garantie) && !is_object($vehicule)) {

            if (!is_float($siege)) {
                $siegePrix = $this->optionsRepo->find(intval($siege))->getPrix();
            } else {
                $siegePrix = $siege;
            }
            if (!is_float($garantie)) {
                $garantiePrix = $this->garantiesRepo->find(intval($garantie))->getPrix();
            } else {
                $garantiePrix = $garantie;
            }

            $vehicule = $this->vehiculeRepo->find(intval($vehicule));
        } else {
            $siegePrix = $siege->getPrix();
            $garantiePrix = $garantie->getPrix();
        }

        $mois = $this->monthName($dateDepart);
        $tarifs = $this->tarifsRepo->findTarifs($vehicule, $mois);
        $duree = $this->calculDuree($dateDepart, $dateRetour);
        $tarif = 0; //initialisation de $tarif
        if (!is_null($tarifs)) {

            if ($duree <= 3) $tarif = $tarifs->getTroisJours();

            if ($duree > 3 && $duree <= 7) $tarif = $tarifs->getSeptJours();

            if ($duree > 7 && $duree <= 15) $tarif = $tarifs->getQuinzeJours();

            if ($duree > 15 && $duree <= 30) $tarif = $tarifs->getTrenteJours();
        }

        if ($tarif != null) {
            $prix = $tarif + $siegePrix + $garantiePrix;
        } else {
            $prix = $siegePrix + $garantiePrix;
        }

        return $prix;
    }
    function calculDuree($dateDepart, $dateRetour)
    {
        $dateDepart = new \DateTime($dateDepart);
        $dateRetour = new \DateTime($dateRetour);
        $duree = date_diff($dateDepart, $dateRetour);

        return $duree->days;
    }
  
    /**
     * @Route("devispdf/{id}", name="devis_pdf", methods={"GET"})
     */  
    public function pdfdevis(Pdf $knpSnappyPdf, Devis $devis, UserRepository $userRepository, VehiculeRepository $vehiculeRepository, DevisRepository $devisRepository)
    {
        // Configure Dompdf according to your needs
        $pdfOptions = new Options();
        $pdfOptions->set('defaultFont', 'Arial');        
        // Instantiate Dompdf with our options
        $dompdf = new Dompdf($pdfOptions);
        $client = new User();
        $vehicule = new Vehicule();
        $client = $devisRepository->findOneBy(['client' => $devis->getClient()]);
        $user = $userRepository->findOneBy(["id" => $client->getId()]);
        $vehicule = $devisRepository->findOneBy(['vehicule' => $devis->getVehicule()]);
        $quantite = $devis->getDateDepart()->diff($devis->getDateRetour());
        $html = $this->renderView('admin/devis/pdfdevis.html.twig', [
            'devis'  => $devis,
            'client' => $user,
            'vehicule' => $vehicule,
            'quantite' => $quantite,
        ]);

        /* return new PdfResponse(
            $knpSnappyPdf->getOutputFromHtml($html),
            'file.pdf'
        ); */
        
        // Load HTML to Dompdf
        $dompdf->loadHtml($html);
        
        // (Optional) Setup the paper size and orientation 'portrait' or 'portrait'
        $dompdf->setPaper('A4', 'portrait');

        // Render the HTML as PDF
        $dompdf->render();

        // Output the generated PDF to Browser (force download)
        $dompdf->stream("devis.pdf", [
            "Attachment" => true,
        ]);
    }
}
