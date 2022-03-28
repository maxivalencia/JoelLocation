<?php

namespace App\Controller;

use App\Classe\Mailjet;
use DateTimeZone;
use Dompdf\Dompdf;
use Dompdf\Options;
use Knp\Snappy\Pdf;
use App\Entity\User;
use App\Entity\Devis;
use App\Form\DevisType;
use App\Entity\Vehicule;
use App\Entity\Reservation;
use App\Service\DateHelper;
use App\Service\TarifsHelper;
use App\Repository\UserRepository;
use App\Form\DevisEditVehiculeType;
use App\Repository\DevisRepository;
use App\Repository\TarifsRepository;
use App\Repository\OptionsRepository;
use App\Repository\GarantieRepository;
use App\Repository\VehiculeRepository;
use App\Form\EditClientReservationType;
use App\Form\Devis\OptionsGarantiesType;
use Doctrine\ORM\EntityManagerInterface;
use App\Controller\ReservationController;
use App\Repository\ReservationRepository;
use Symfony\Component\HttpFoundation\Request;
// Include Dompdf required namespaces
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Knp\Bundle\SnappyBundle\Snappy\Response\PdfResponse;
use MercurySeries\FlashyBundle\FlashyNotifier;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Security\Core\Encoder\PasswordHasherEncoder;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Serializer\SerializerInterface;


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
    private $tarifsHelper;
    private $dateHelper;
    private $em;
    private $mailjet;
    private $flashy;


    public function __construct(FlashyNotifier $flashy, Mailjet $mailjet, EntityManagerInterface $em, DateHelper $dateHelper, TarifsHelper $tarifsHelper,  UserRepository $userRepo, DevisRepository $devisRepo, ReservationRepository $reservationRepo, VehiculeRepository $vehiculeRepo,   TarifsRepository $tarifsRepo, OptionsRepository $optionsRepo, GarantieRepository $garantiesRepo, ReservationController $reservController)
    {

        $this->reservationRepo = $reservationRepo;
        $this->vehiculeRepo = $vehiculeRepo;
        $this->userRepo = $userRepo;
        $this->devisRepo = $devisRepo;
        $this->tarifsRepo = $tarifsRepo;
        $this->garantiesRepo = $garantiesRepo;
        $this->optionsRepo = $optionsRepo;
        $this->reservController = $reservController;
        $this->dateHelper = $dateHelper;
        $this->tarifsHelper = $tarifsHelper;
        $this->em = $em;
        $this->mailjet = $mailjet;
        $this->flashy = $flashy;
    }

    /**
     * @Route("/devis", name="devis_index")
     */
    public function index(): Response
    {
        $devis = $this->devisRepo->findBy([], ["id" => "ASC"]);

        return $this->render('admin/devis/index.html.twig', [
            'devis' => $devis,

        ]);
    }


    /**
     * @Route("/devis/new", name="devis_new", methods={"GET","POST"})
     */
    public function newDevis(Request $request): Response
    {

        $idClient =  $request->query->get('idClient');

        if ($request->isXmlHttpRequest() || $idClient != null) {

            $devis = new Devis();

            $arrayOptionsID = [];
            $arrayGarantiesID = [];
            $options = [];
            $garanties = [];

            $agenceDepart = $request->query->get('agenceDepart');
            $agenceRetour = $request->query->get('agenceRetour');
            $lieuSejour = $request->query->get('lieuSejour');
            $dateTimeDepart = $request->query->get('dateTimeDepart');
            $dateTimeRetour = $request->query->get('dateTimeRetour');
            $vehiculeIM = $request->query->get('vehiculeIM');
            $conducteur = $request->query->get('conducteur');
            $arrayOptionsID = (array) $request->query->get('arrayOptionsID');
            $arrayGarantiesID = (array)$request->query->get('arrayGarantiesID');

            $dateDepart = new \DateTime($dateTimeDepart);
            $dateRetour = new \DateTime($dateTimeRetour);

            $vehicule = $this->vehiculeRepo->findByIM($vehiculeIM);
            $client = $this->userRepo->find($idClient);
            $duree = $this->dateHelper->calculDuree($dateDepart, $dateRetour);
            $tarifVehicule = $this->tarifsHelper->calculTarifVehicule($dateDepart, $dateRetour, $vehicule);

            $devis->setVehicule($vehicule);
            $devis->setClient($client);
            $devis->setAgenceDepart($agenceDepart);
            $devis->setAgenceRetour($agenceRetour);
            $devis->setDateDepart($dateDepart);
            $devis->setDateRetour($dateRetour);

            //loop sur id des options
            if ($arrayOptionsID != []) {
                for ($i = 0; $i < count($arrayOptionsID); $i++) {

                    $id = $arrayOptionsID[$i];
                    $option = $this->optionsRepo->find($id);
                    array_push($options, $option);
                    $devis->addOption($option);
                }
            }

            //loop sur id des garanties
            if ($arrayGarantiesID != []) {

                for ($i = 0; $i < count($arrayGarantiesID); $i++) {

                    $id = $arrayGarantiesID[$i];
                    $garantie = $this->garantiesRepo->find($id);
                    array_push($garanties, $garantie);
                    $devis->addGaranty($garantie);
                }
            }

            $devis->setConducteur($conducteur);
            $devis->setLieuSejour($lieuSejour);
            $devis->setDuree($duree);
            $devis->setDateCreation($this->dateHelper->dateNow());
            $devis->setTransformed(false);
            $devis->setTarifVehicule($tarifVehicule);

            // ajout reference dans Entity RESERVATION (CPTGP + year + month + ID)
            $lastID = $this->devisRepo->findBy(array(), array('id' => 'DESC'), 1);
            if ($lastID == null) {
                $currentID = 1;
            } else {

                $currentID = $lastID[0]->getId() + 1;
            }
            $devis->setNumeroDevis($currentID);

            $prix = $this->tarifsHelper->calculTarifTotal($tarifVehicule, $options, $garanties);

            $devis->setPrix($prix);

            $emDevis = $this->getDoctrine()->getManager();
            $emDevis->persist($devis);
            $emDevis->flush();

            return $this->redirectToRoute('devis_index');
        }

        $devis = $this->devisRepo->findAll();

        return $this->render('admin/devis/index.html.twig', [
            'devis' => $devis
        ]);
    }


    /**
     * @Route("devis/details/{id}", name="devis_show", methods={"GET"})
     */
    public function show(Devis $devis): Response
    {

        return $this->render('admin/devis/details.html.twig', [
            'devis' => $devis,
        ]);
    }

    /**
     * @Route("devis/client/{id}", name="client_devis_show", methods={"GET"})
     */
    public function client_devis_show(Devis $devis): Response
    {

        return $this->render('client/reservation/details.html.twig', [
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
            $tarifVehicule = $this->tarifsHelper->calculTarifVehicule($dateDepart, $dateRetour, $vehicule);
            $prix = $this->tarifsHelper->calculTarifTotal($tarifVehicule, $siege, $garantie);
            $devis->setPrix($prix);
            // $devis->setVehicule($this->vehiculeRepo->find($vehicule));
            $devis->setDuree($this->dateHelper->calculDuree($dateDepart, $dateRetour));
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

            $dateDepart = $this->dateHelper->newDate($dateDepart);
            $dateRetour = $this->dateHelper->newDate($dateRetour);

            $vehicule = $this->vehiculeRepo->find($vehicule);

            $tarifVehicule = $this->tarifsHelper->calculTarifVehicule($dateDepart, $dateRetour, $vehicule);
            $tarifTotal = $this->tarifsHelper->calculTarifTotal($tarifVehicule, $devis->getOptions(), $devis->getGaranties());

            $devis->setPrix($tarifTotal);
            $devis->setVehicule($vehicule);
            $devis->setDuree($this->dateHelper->calculDuree($dateDepart, $dateRetour));
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
        //boucle pour ajout options 
        foreach ($devis->getOptions() as $option) {
            $reservation->addOption($option);
        }

        //boucle pour ajout garantie 

        foreach ($devis->getGaranties() as $garantie) {
            $reservation->addGaranty($garantie);
        }


        $reservation->setPrix($devis->getPrix());
        $reservation->setDateReservation(new \DateTime('NOW'));
        $reservation->setCodeReservation('devisTransformé');

        // ajout reference dans Entity RESERVATION (CPTGP + year + month + ID)
        $lastID = $this->reservationRepo->findBy(array(), array('id' => 'DESC'), 1);
        if ($lastID == null) {
            $currentID = 1;
        } else {
            $currentID = $lastID[0]->getId() + 1;
        }

        $reservation->setRefRes("CPTGP", $currentID);
        $entityManager = $this->reservController->getDoctrine()->getManager();
        $entityManager->persist($reservation);
        $entityManager->flush();
        // dump($reservation);
        // die();
        return $this->redirectToRoute('reservation_index');
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
        //$quantite = $devis->getDateDepart()->diff($devis->getDateRetour());
        $quantite = date_diff($devis->getDateDepart(), $devis->getDateRetour());
        $logo = $this->getParameter('logo') . '/Joel-Location-new.png';
        $logo_data = base64_encode(file_get_contents($logo));
        $logo_src = 'data:image/png;base64,' . $logo_data;
        $prixunitaire = $devis->getPrix();
        $prixht = $prixunitaire * $quantite->d;
        $tva = 8.5 / 100;
        $montant = $tva * $prixht;
        $total = $prixht * (1 + $tva);
        $html = $this->renderView('admin/devis/pdfdevis.html.twig', [
            'devis'  => $devis,
            'client' => $user,
            'vehicule' => $vehicule,
            'quantite' => $quantite,
            'logo' => $logo_src,
            'prix_unitaire' => $prixunitaire,
            'tva' => $tva,
            'montanttva' => $montant,
            'prixht' => $prixht,
            'total' => $total,
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


    // fonction dans details devis -*/****************************** */


    /**
     *  @Route("devis/modifier-infos-client/{id}", name="devis_infosClient_edit", methods={"GET","POST"},requirements={"id":"\d+"})
     *
     */
    public function editInfosClient(Request $request, Devis $devis): Response
    {
        //form pour client
        $client = $devis->getClient();
        $form = $this->createForm(EditClientReservationType::class, $client);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->persist($client);
            $this->em->flush();
            $this->flashy->success("La réservation a bien été modifié");
            return $this->redirectToRoute('devis_show', ['id' => $devis->getId()]);
        }

        return $this->render('admin/devis/infos_client/edit.html.twig', [

            'form' => $form->createView(),
            'devis' => $devis,

        ]);
    }

    /**
     *  @Route("devis/modifier-options-garanties/{id}", name="devis_optionsGaranties_edit", methods={"GET","POST"},requirements={"id":"\d+"})
     */
    public function editOptionsGaranties(Request $request, Devis $devis, SerializerInterface $serializerInterface): Response
    {

        $form = $this->createForm(OptionsGarantiesType::class, $devis);
        $garanties = $this->garantiesRepo->findAll();
        $options = $this->optionsRepo->findAll();
        $form->handleRequest($request);

        //serializer options et garanties de devis
        $dataOptions = [];
        foreach ($devis->getOptions() as $key => $option) {
            $dataOptions[$key]['id'] =  $option->getId();
            $dataOptions[$key]['appelation'] = $option->getAppelation();
            $dataOptions[$key]['description'] = $option->getDescription();
            $dataOptions[$key]['type'] = $option->getType();
            $dataOptions[$key]['prix'] = $option->getPrix();
        }

        $dataGaranties = [];
        foreach ($devis->getGaranties() as $key => $garantie) {
            $dataGaranties[$key]['id'] =  $garantie->getId();
            $dataGaranties[$key]['appelation'] = $garantie->getAppelation();
            $dataGaranties[$key]['description'] = $garantie->getDescription();
            $dataGaranties[$key]['prix'] = $garantie->getPrix();
        }

        $allOptions = [];
        foreach ($this->optionsRepo->findAll() as $key => $option) {
            $allOptions[$key]['id'] =  $option->getId();
            $allOptions[$key]['appelation'] = $option->getAppelation();
            $allOptions[$key]['description'] = $option->getDescription();
            $allOptions[$key]['prix'] = $option->getPrix();
            $allOptions[$key]['type'] = $option->getType();
        }


        $allGaranties = [];
        foreach ($this->garantiesRepo->findAll() as $key => $garantie) {
            $allGaranties[$key]['id'] =  $garantie->getId();
            $allGaranties[$key]['appelation'] = $garantie->getAppelation();
            $allGaranties[$key]['description'] = $garantie->getDescription();
            $allGaranties[$key]['prix'] = $garantie->getPrix();
        }

        if ($request->get('editedOptionsGaranties') == "true") {

            $checkboxOptions = $request->get("checkboxOptions");
            $checkboxGaranties = $request->get("checkboxGaranties");

            if ($checkboxOptions != []) {
                // tous enlever et puis entrer tous les options
                foreach ($devis->getOptions() as $option) {
                    $devis->removeOption($option);
                }
                for ($i = 0; $i < count($checkboxOptions); $i++) {
                    $devis->addOption($this->optionsRepo->find($checkboxOptions[$i]));
                }
                $this->em->flush();
            } else {
                // si il y a des options, les enlever
                if (count($devis->getOptions()) > 0) {
                    foreach ($devis->getOptions() as $option) {
                        $devis->removeOption($option);
                    }
                }
                $this->em->flush();
            }

            if ($checkboxGaranties != []) {
                // tous enlever et puis entrer tous les garanties
                foreach ($devis->getGaranties() as $garantie) {
                    $devis->removeGaranty($garantie);
                }
                for ($i = 0; $i < count($checkboxGaranties); $i++) {
                    $devis->addGaranty($this->garantiesRepo->find($checkboxGaranties[$i]));
                }
                $this->em->flush();
            } else {
                // si il y a des garanties, les enlever
                if (count($devis->getGaranties()) > 0) {
                    foreach ($devis->getGaranties() as $garantie) {
                        $devis->removeGaranty($garantie);
                    }
                }
                $this->em->flush();
            }
            $devis->setPrixGaranties($this->tarifsHelper->sommeTarifsGaranties($devis->getGaranties()));
            $devis->setPrixOptions($this->tarifsHelper->sommeTarifsOptions($devis->getOptions()));
            $devis->setPrix($devis->getTarifVehicule() + $devis->getPrixOptions() + $devis->getPrixGaranties());

            $this->em->flush();
            return $this->redirectToRoute('devis_show', ['id' => $devis->getId()]);
        }

        return $this->render('admin/devis/options_garanties/edit.html.twig', [
            'form' => $form->createView(),
            'devis' => $devis,
            'garanties' => $garanties,
            'options' => $options,
            'routeReferer' => 'reservation_show',
            'dataOptions' => $dataOptions,
            'dataGaranties' => $dataGaranties,
            'allOptions' => $allOptions,
            'allGaranties' => $allGaranties,

        ]);
    }




    /**
     * @Route("devis/envoi-identification-connexion/{id}", name="devis_ident_connex", methods={"GET","POST"},requirements={"id":"\d+"})
     */
    public function envoyerIdentConnex(Request $request, Devis $devis, UserPasswordEncoderInterface $passwordEncoder): Response
    {

        $mail = $devis->getClient()->getMail();
        $nom = $devis->getClient()->getNom();
        $mdp = uniqid();
        $content = "Bonjour, " . '<br>' .  "voici vos identifications de connexion." . '<br>' . " Mot de passe: " . $mdp . '<br>' . "Email : votre email";

        $devis->getClient()->setPassword($passwordEncoder->encodePassword(
            $devis->getClient(),
            $mdp
        ));
        $this->em->flush();

        $this->mailjet->send($mail, $nom, "Identifiants de connexion", $content);

        $this->flashy->success("Les identifians de connexion du client ont été envoyés");
        return $this->redirectToRoute('devis_show', ['id' => $devis->getId()]);
    }
    /**
     * @Route("devis/envoyer-devis/{id}", name="envoyer_devis", methods={"GET","POST"},requirements={"id":"\d+"})
     */
    public function envoyerDevis(Request $request, Devis $devis): Response
    {

        $mail = $devis->getClient()->getMail();
        $nom = $devis->getClient()->getNom();

        $url   = $this->generateUrl('devis_pdf', ['id' => $devis->getId()]);
        $url = "https://joellocation.com" . $url;
        $linkDevis = "<a href='" . $url . "'>lien</a>";

//        $content = "Bonjour, " . '<br>' . "Vous pouvez télécharger votre devis N°" . $devis->getNumero() . "en cliquant sur ce <a href='" . $url . "'>lien</a>.";

        $this->mailjet->envoiDevis(
            $devis->getClient()->getPrenom().' '.$devis->getClient()->getNom(),
            $devis->getClient()->getMail(),
            "Téléchargement de devis",
            $this->dateHelper->frenchDate($devis->getDateCreation()),
            $devis->getNumero(),
            $devis->getVehicule()->getMarque() ." ".$devis->getVehicule()->getModele(),
            $this->dateHelper->frenchDate($devis->getDateDepart())." ".$this->dateHelper->frenchHour($devis->getDateDepart()),
            $this->dateHelper->frenchDate($devis->getDateRetour())." ".$this->dateHelper->frenchHour($devis->getDateRetour()),
            $linkDevis
        );

        $this->flashy->success("L'url de téléchargement du devis N°" . $devis->getNumero() . " a été envoyé");
        return $this->redirectToRoute('devis_show', ['id' => $devis->getId()]);
    }
}
