<?php

namespace App\Controller;

use App\Entity\Garantie;
use App\Entity\User;
use App\Form\UserType;
use App\Entity\Vehicule;
use App\Form\VehiculeType;
use App\Entity\Reservation;
use App\Form\StopSalesType;
use App\Form\UserClientType;
use App\Form\ReservationType;
use App\Form\EditStopSalesType;
use App\Repository\GarantieRepository;
use App\Repository\OptionsRepository;
use App\Repository\UserRepository;
use App\Repository\VehiculeRepository;
use App\Repository\ReservationRepository;
use App\Repository\TarifsRepository;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

/**
 * @Route("/reservation")
 */
class ReservationController extends AbstractController
{
    private $userRepo;
    private $reservationRepo;
    private $dateTimestamp;
    private $vehiculeRepo;
    private $optionsRepo;
    private $garantiesRepo;
    private $tarifsRepo;

    public function __construct(TarifsRepository $tarifsRepo, ReservationRepository $reservationRepo,  UserRepository $userRepo, VehiculeRepository $vehiculeRepo, OptionsRepository $optionsRepo, GarantieRepository $garantiesRepo)
    {

        $this->reservationRepo = $reservationRepo;
        $this->vehiculeRepo = $vehiculeRepo;
        $this->optionsRepo = $optionsRepo;
        $this->garantiesRepo = $garantiesRepo;
        $this->userRepo = $userRepo;
        $this->tarifsRepo = $tarifsRepo;
    }

    /**
     * @Route("/vehiculeDispoFonctionDates", name="vehiculeDispoFonctionDates",methods={"GET","POST"}))
     */
    public function vehiculeDispoFonctionDates(Request $request)
    {

        $dateDebutday = $request->query->get('dateDebutday');
        $dateDebutmonth = $request->query->get('dateDebutmonth');
        $dateDebutyear = $request->query->get('dateDebutyear');
        $dateDebuthours = $request->query->get('dateDebuthours');
        $dateDebutminutes = $request->query->get('dateDebutminutes');
        $dateFinday = $request->query->get('dateFinday');
        $dateFinmonth = $request->query->get('dateFinmonth');
        $dateFinyear = $request->query->get('dateFinyear');
        $dateFinhours = $request->query->get('dateFinhours');
        $dateFinminutes = $request->query->get('dateFinminutes');

        $dateDebut = date("Y-m-d H:i", mktime($dateDebuthours, $dateDebutminutes, 00, $dateDebutmonth, $dateDebutday, $dateDebutyear));
        $dateFin = date("Y-m-d H:i", mktime($dateFinhours, $dateFinminutes, 00, $dateFinmonth, $dateFinday, $dateFinyear));


        $dateDebut = new \DateTime($dateDebut);

        $dateFin = new \DateTime($dateFin);


        $datas = array();
        foreach ($this->getVehiculesDispo($dateDebut, $dateFin) as $key => $vehicule) {
            $datas[$key]['id'] = $vehicule->getId();
            $datas[$key]['marque'] = $vehicule->getMarque()->getLibelle();
            $datas[$key]['modele'] = $vehicule->getModele();
            $datas[$key]['immatriculation'] = $vehicule->getImmatriculation();
        }

        return new JsonResponse($datas);
    }


    /**
     * @Route("/listeVehicules", name="listeVehicules",methods={"GET","POST"}))
     */
    public function listeVehicules(Request $request)
    {

        $dateDepart = $request->query->get('dateDepart');
        $dateRetour = $request->query->get('dateRetour');
        $dateDepart = new \DateTime($dateDepart);
        $dateRetour = new \DateTime($dateRetour);
        $datas = array();
        // dump($this->reservationRepo->findReservationIncludeDates($dateDepart, $dateRetour));
        // die();

        foreach ($this->reservationRepo->findReservationIncludeDates($dateDepart, $dateRetour) as $key => $reservation) {
            $datas[$key]['id'] = $reservation->getVehicule()->getId();
            $datas[$key]['marque'] = $reservation->getVehicule()->getMarque()->getLibelle();
            $datas[$key]['modele'] = $reservation->getVehicule()->getModele();
            $datas[$key]['immatriculation'] = $reservation->getVehicule()->getImmatriculation();
        }

        return new JsonResponse($datas);
    }
    public function getVehiculesDispo($dateDebut, $dateFin)
    {
        $vehicules = $this->vehiculeRepo->findAll();
        $reservations = $this->reservationRepo->findReservationIncludeDates($dateDebut, $dateFin);
        // dd($reservations);

        $i = 0;
        $vehiculeDispo = [];

        // code pour vehicule avec reservation , mila manao condition amle tsy misy reservation mihitsy
        foreach ($vehicules as $vehicule) {
            foreach ($reservations as $reservation) {
                if ($vehicule == $reservation->getVehicule()) {
                    $i++;
                }
            }
            if ($i == 0) {
                $vehiculeDispo[] = $vehicule;
            }
            $i = 0;
        }
        return $vehiculeDispo;
    }

    /**
     * @Route("/", name="reservation_index", methods={"GET"})
     */
    public function index(ReservationRepository $reservationRepository, Request $request, PaginatorInterface $paginator): Response
    {

        $reservations = $reservationRepository->findReservationsSansStopSales();

        $pagination = $paginator->paginate(
            $reservations, /* query NOT result */
            $request->query->getInt('page', 1)/*page number*/,
            50/*limit per page*/
        );
        return $this->render('admin/reservation/crud/index.html.twig', [
            'reservations' => $pagination,
        ]);
    }



    /**
     * @Route("/new", name="reservation_new", methods={"GET","POST"})
     */
    public function new(Request $request, VehiculeRepository $vehiculeRepository): Response
    {

        $reservation = new Reservation();
        $form = $this->createForm(ReservationType::class, $reservation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $vehicule = $vehiculeRepository->find($request->request->get('select'));
            $entityManager = $this->getDoctrine()->getManager();
            $reservation->setVehicule($vehicule);
            $entityManager->persist($reservation);
            $entityManager->flush();

            return $this->redirectToRoute('reservation_index');
        }

        return $this->render('admin/reservation/crud/new.html.twig', [
            'reservation' => $reservation,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/newReservation", name="reserverVenteComptoir",  methods={"GET","POST"})
     */
    public function reserverVenteComptoir(Request $request): Response
    {
        $reservation = new Reservation();

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

            $reservation->setVehicule($vehicule);
            $reservation->setClient($client);
            $reservation->setAgenceDepart($agenceDepart);
            $reservation->setAgenceRetour($agenceRetour);
            $reservation->setDateDebut(new \DateTime($dateTimeDepart));
            $reservation->setDateFin(new \DateTime($dateTimeRetour));
            $reservation->setGarantie($garantie);
            $reservation->setSiege($siege);
            $reservation->setConducteur($conducteur);
            $reservation->setLieu($lieuSejour);
            $reservation->setDuree($duree);
            $reservation->setDateReservation(new \DateTime('NOW'));
            $reservation->setCodeReservation($agenceDepart);

            $prix = $this->calculTarif($dateTimeDepart, $dateTimeRetour, $siege, $garantie, $vehicule);

            $reservation->setPrix($prix);

            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($reservation);
            $entityManager->flush();

            return $this->redirectToRoute('reservation_index');
        }
        return $this->redirectToRoute('reservation_index');
        // $reservations = $this->reservationRepo->findAll();

        // return $this->render('admin/devis/index.html.twig', [
        //     'reservations' => $reservations
        // ]);
    }


    /**
     * @Route("/stop_sales", name="stop_sales", methods={"GET","POST"})
     */
    public function stop_sales(Request $request, ReservationRepository $reservationRepository,  UserRepository $userRepo,  VehiculeRepository $vehiculeRepository): Response
    {

        $listeStopSales = new Reservation();
        $reservation = new Reservation();

        $listeStopSales =  $reservationRepository->findStopSales();
        $super_admin = $userRepo->findSuperAdmin();

        $formStopSales = $this->createForm(StopSalesType::class, $reservation);

        $formStopSales->handleRequest($request);

        if ($formStopSales->isSubmitted() && $formStopSales->isValid()) {

            $vehicule = $vehiculeRepository->find($request->request->get('select'));
            $entityManager = $this->getDoctrine()->getManager();
            $reservation->setVehicule($vehicule);
            $reservation->setCodeReservation('stopSale');
            $reservation->setAgenceDepart('garage');
            $reservation->setClient($super_admin);
            $reservation->setDateReservation(new \DateTime('NOW'));
            $entityManager->persist($reservation);
            $entityManager->flush();

            return $this->redirectToRoute('stop_sales');
        }

        return $this->render('admin/stop_sales_vehicules/index.html.twig', [
            'listeStopSales' => $listeStopSales,
        ]);
    }
    /**
     * @Route("/stopSalesNew", name="stopSalesNew", methods={"GET","POST"})
     */
    public function stopSalesNew(Request $request, VehiculeRepository $vehiculeRepository,  UserRepository $userRepo): Response
    {

        $super_admin = $userRepo->findSuperAdmin();
        $reservation = new Reservation();
        $formStopSales = $this->createForm(StopSalesType::class, $reservation);
        $formStopSales->handleRequest($request);

        if ($formStopSales->isSubmitted() && $formStopSales->isValid()) {

            $entityManager = $this->getDoctrine()->getManager();
            $reservation->setClient($super_admin);
            $reservation->setDateReservation(new \DateTime('NOW'));
            $entityManager->persist($reservation);
            $entityManager->flush();

            return $this->redirectToRoute('stop_sales');
        }

        return $this->render('admin/stop_sales_vehicules/new.html.twig', [
            'formStopSales' => $formStopSales->createView(),

        ]);
    }


    /**
     * @Route("/{id}/editStopSale", name="stopSale_edit", methods={"GET","POST"}, requirements={"id":"\d+"})
     */
    public function editStopSale(Request $request, Reservation $reservation, ReservationRepository $reservationRepository): Response
    {
        $listeStopSales =  $reservationRepository->findStopSales();

        $formStopSales = $this->createForm(StopSalesType::class, $reservation);
        $formStopSales->handleRequest($request);

        if ($formStopSales->isSubmitted() && $formStopSales->isValid()) {
            $vehicule = $this->vehiculeRepo->find($request->request->get('select'));
            $reservation->setVehicule($vehicule);
            $this->getDoctrine()->getManager()->flush();
            return $this->redirectToRoute('stop_sales');
        }

        return $this->render('admin/stop_sales_vehicules/edit.html.twig', [
            'listeStopSales' => $listeStopSales,
            'formStopSales' => $formStopSales->createView(),
        ]);
    }

    /**
     * @Route("/{id}/delete", name="stopSale_delete", methods={"DELETE"},requirements={"id":"\d+"})
     */
    public function stopSaleDelete(Request $request, Reservation $reservation): Response
    {
        if ($this->isCsrfTokenValid('delete' . $reservation->getId(), $request->request->get('_token'))) {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($reservation);
            $entityManager->flush();
        }

        return $this->redirectToRoute('stop_sales');
    }

    /**
     * @Route("/{id}", name="reservation_show", methods={"GET"},requirements={"id":"\d+"})
     */
    public function show(Reservation $reservation): Response
    {
        return $this->render('admin/reservation/crud/show.html.twig', [
            'reservation' => $reservation,
        ]);
    }

    /**
     * @Route("/{id}/edit", name="reservation_edit", methods={"GET","POST"},requirements={"id":"\d+"})
     */
    public function edit(Request $request, Reservation $reservation): Response
    {
        $form = $this->createForm(ReservationType::class, $reservation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $vehicule = $this->vehiculeRepo->find($request->request->get('select'));
            $reservation->setVehicule($vehicule);
            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('reservation_index');
        }

        return $this->render('admin/reservation/crud/edit.html.twig', [
            'reservation' => $reservation,
            'imVeh' => $reservation->getVehicule()->getImmatriculation(), //utile pour val par défaut select
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="reservation_delete", methods={"DELETE"},requirements={"id":"\d+"})
     */
    public function delete(Request $request, Reservation $reservation): Response
    {
        if ($this->isCsrfTokenValid('delete' . $reservation->getId(), $request->request->get('_token'))) {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($reservation);
            $entityManager->flush();
        }

        return $this->redirectToRoute('reservation_index');
    }

    /**
     * @Route("/recherchesimple", name="recherche_simple", methods={"GET", "POST"})
     */
    public function rechercheSimple(Request $request, UserRepository $userRepository, ReservationRepository $reservationRepository): Response
    {
        $recherche = $request->query->get('recherche');
        // dd($request);
        // die();
        $reservation[] = new Reservation();

        if ($recherche != null) {
            $client_id = (int)$recherche;
            $client = new User();
            $reservation[] = new Reservation();
            //if($client_id){
            $client = $userRepository->findOneBy(["id" => $client_id]);
            //}
            if ($client == null) {
                $client = $userRepository->findOneBy(["nom" => $recherche]);
            }
            if ($client != null) {
                $reservation = $reservationRepository->findBy(["client" => $client]);
            } else {
                $reservation = $reservationRepository->findBy(["code_reservation" => $recherche]);
            }
            $datas = array();

            foreach ($reservation as $key => $res) {
                if ($res->getDateFin() < new \Datetime('now')) {
                    $datas[$key]['status'] = 0; //terminé
                } else {
                    $datas[$key]['status'] = 1; //en cours
                }
                $datas[$key]['id'] = $res->getId();
                $datas[$key]['prix'] = $res->getPrix();
                $datas[$key]['dateDepart'] = $res->getDateDebut()->format('d-m-Y H:i');
                $datas[$key]['dateRetour'] = $res->getDateFin()->format('d-m-Y H:i');
                $datas[$key]['dateResa'] = $res->getDateReservation()->format('d-m-Y H:i');
                $datas[$key]['nomPrenomClient'] = $res->getClient()->getNom() . " " . $res->getClient()->getPrenom();
                $datas[$key]['mailClient'] = $res->getClient()->getMail();
                $datas[$key]['dureeResa'] = \date_diff($res->getDateFin(), $res->getDateDebut())->format('%d');
                $datas[$key]['codeResa'] = $res->getCodeReservation();
                $datas[$key]['vehicule'] = $res->getVehicule()->getMarque()->getLibelle() . " " . $res->getVehicule()->getModele() . " " . $res->getVehicule()->getImmatriculation();
            }

            // dd($datas);
            // die();

            return new JsonResponse($datas);
        }
        return $this->redirectToRoute('reservation_index');
    }

    /**
     * @Route("/rechercheimmatriculation", name="recherche_immatriculation", methods={"GET", "POST"})
     */
    public function rechercheImmatriculation(Request $request): Response
    {

        // dump($request);
        // die();

        $idVehicule = $request->query->get('idVehicule');
        $date = $request->query->get('date');

        if ($idVehicule != null && $date != null) {
            $date = new \DateTime($date);
            $vehicule = $this->vehiculeRepo->find($idVehicule);

            // dump($date, $vehicule);
            // die();

            $reservations = new Reservation();
            $reservations = $this->reservationRepo->findRechercheIM($vehicule, $date);

            $datas = array();

            foreach ($reservations as $key => $res) {
                if ($res->getDateFin() < new \Datetime('now')) {
                    $datas[$key]['status'] = 0; //terminé
                } else {
                    $datas[$key]['status'] = 1; //en cours
                }
                $datas[$key]['id'] = $res->getId();
                $datas[$key]['prix'] = $res->getPrix();
                $datas[$key]['dateDepart'] = $res->getDateDebut()->format('d-m-Y H:i');
                $datas[$key]['dateRetour'] = $res->getDateFin()->format('d-m-Y H:i');
                $datas[$key]['dateResa'] = $res->getDateReservation()->format('d-m-Y H:i');
                $datas[$key]['nomPrenomClient'] = $res->getClient()->getNom() . " " . $res->getClient()->getPrenom();
                $datas[$key]['mailClient'] = $res->getClient()->getMail();
                $datas[$key]['dureeResa'] = \date_diff($res->getDateFin(), $res->getDateDebut())->format('%d');
                $datas[$key]['codeResa'] = $res->getCodeReservation();
                $datas[$key]['vehicule'] = $res->getVehicule()->getMarque()->getLibelle() . " " . $res->getVehicule()->getModele() . " " . $res->getVehicule()->getImmatriculation();
            }

            return new JsonResponse($datas);
        }
        return $this->redirectToRoute('reservation_index');

        // if ($immatriculation != null) {
        //     $vehicule = $vehiculeRepository->findOneBy(["immatriculation" => $immatriculation]);
        //     $reservation = $reservationRepository->findBy(["vehicule" => $vehicule]);
        // }
        // return $this->redirectToRoute('reservation_index');
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
}
