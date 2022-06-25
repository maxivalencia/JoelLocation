<?php

namespace App\Classe;

use App\Entity\Devis;
use App\Entity\Reservation;
use App\Service\DateHelper;
use App\Service\TarifsHelper;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\ModeReservationRepository;
use App\Repository\ReservationRepository;

class ReserverDevis
{
    private $em;
    private $tarifsHelper;
    private $dateHelper;
    private $modeReservationRepo;
    private $reservRepo;

    public function __construct(
        EntityManagerInterface $em,
        TarifsHelper $tarifsHelper,
        DateHelper $dateHelper,
        ModeReservationRepository $modeReservationRepo,
        ReservationRepository $reservRepo
    ) {
        $this->em = $em;
        $this->tarifsHelper = $tarifsHelper;
        $this->dateHelper = $dateHelper;
        $this->modeReservationRepo = $modeReservationRepo;
        $this->reservRepo = $reservRepo;
    }

    public function reserver(Devis $devis, $stripeSessionId = "null")
    {
        $reservation = new Reservation();
        $reservation->setVehicule($devis->getVehicule());
        $reservation->setStripeSessionId($stripeSessionId);
        $reservation->setClient($devis->getClient());
        $reservation->setDateDebut($devis->getDateDepart());
        $reservation->setDateFin($devis->getDateRetour());
        $reservation->setAgenceDepart($devis->getAgenceDepart());
        $reservation->setAgenceRetour($devis->getAgenceRetour());
        $reservation->setNumDevis($devis->getId()); //reference numero devis reservé
        //boucle pour ajout options 
        foreach ($devis->getOptions() as $option) {
            $reservation->addOption($option);
        }

        //boucle pour ajout garantie 
        foreach ($devis->getGaranties() as $garantie) {
            $reservation->addGaranty($garantie);
        }

        $reservation->setPrix($devis->getPrix());
        $reservation->setPrixOptions($this->tarifsHelper->sommeTarifsOptions($devis->getOptions()));
        $reservation->setPrixGaranties($this->tarifsHelper->sommeTarifsOptions($devis->getGaranties()));
        $reservation->setDuree($this->dateHelper->calculDuree($devis->getDateDepart(), $devis->getDateRetour()));
        $reservation->setTarifVehicule($this->tarifsHelper->calculTarifVehicule($devis->getDateDepart(), $devis->getDateRetour(), $devis->getVehicule()));
        $reservation->setPrix($devis->getPrix());
        $reservation->setDateReservation($this->dateHelper->dateNow());
        $reservation->setCodeReservation('devisTransformé');
        $reservation->setArchived(false);
        $reservation->setCanceled(false);
        $reservation->setModeReservation($this->modeReservationRepo->findOneBy(['libelle' => 'WEB']));
        // ajout reference dans Entity RESERVATION (CPTGP + year + month + ID)
        $lastID = $this->reservRepo->findBy(array(), array('id' => 'DESC'), 1);
        if ($lastID == null) {
            $currentID = 1;
        } else {
            $currentID = $lastID[0]->getId() + 1;
        }

        $reservation->setRefRes($reservation->getModeReservation()->getLibelle(), $currentID);
        $devis->setTransformed(true);

        $this->em->persist($reservation);
        $this->em->persist($devis);
        $this->em->flush();
        // dump($reservation);
        // die();

        //envoi de mail de confirmation de reservation
        // $this->mailjet->confirmationReservation(
        //     $reservation->getClient()->getNom(),
        //     $reservation->getClient()->getMail(),
        //     'Confirmation de Réservation',
        //     $reservation->getDateReservation()->format('d/m/Y'),
        //     $reservation->getReference(),
        //     $reservation->getVehicule()->getMarque() . " " . $reservation->getVehicule()->getModele(),
        //     $reservation->getDateDebut()->format('d/m/Y H:i'),
        //     $reservation->getDateFin()->format('d/m/Y H:i')
        // );
        // $this->mail->confirmationPaiement(
        //     $reservation->getClient()->getNom(),
        //     $reservation->getClient()->getMail(),
        //     'Confirmation de paiement',
        //     $reservation->getDateReservation()->format('d/m/Y'),
        //     $reservation->getReference(),
        //     $reservation->getVehicule()->getMarque() . " " . $reservation->getVehicule()->getModele(),
        //     $reservation->getDateDebut()->format('d/m/Y H:i'),
        //     $reservation->getDateFin()->format('d/m/Y H:i'),
        //     $reservation->getPrix(),
        //     //à corriger car il n'y plus de paiement en ligne
        //     0,
        // );
    }
}
