<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\ClientEditType;
use App\Form\UserType;
use App\Form\UserEditType;
use App\Form\UserClientType;
use App\Repository\UserRepository;
use App\Service\DateHelper;
use DateTimeZone;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Knp\Component\Pager\PaginatorInterface;
use MercurySeries\FlashyBundle\FlashyNotifier;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * @Route("backoffice/utilisateurs")
 */
class UserController extends AbstractController
{
    private $dateHelper;
    private $flashy;
    private $passwordEncoder;

    public function __construct(UserPasswordEncoderInterface $passwordEncoder, FlashyNotifier $flashy, DateHelper $dateHelper)
    {
        $this->dateHelper = $dateHelper;
        $this->flashy = $flashy;
        $this->passwordEncoder = $passwordEncoder;
    }

    /**
     * @Route("/client", name="client_index", methods={"GET"})
     */
    public function index(UserRepository $userRepository, Request $request, PaginatorInterface $paginator): Response
    {
        $clients = array();
        $users = $userRepository->findBy([], ["date_inscription" => "DESC"]);
        $pagination = $paginator->paginate(
            $userRepository->findBy([], ["date_inscription" => "ASC"]), /* query NOT result */
            $request->query->getInt('page', 1)/*page number*/,
            20/*limit per page*/
        );
        foreach ($users  as $user) {

            if (in_array('ROLE_CLIENT', $user->getRoles())) {
                array_push($clients, $user);
            }
        }

        return $this->render('admin/user/index.html.twig', [
            'clients' => $clients,
        ]);
    }

    /**
     * @Route("/listeclients", name="listeclients", methods={"GET"})
     */
    public function listeclients(UserRepository $userRepository, Request $request)
    {
        $clients = array();
        $data = array();
        $clients = $userRepository->findClients();

        foreach ($clients as $key => $client) {

            $data[$key]['id'] = $client->getId();
            $data[$key]['nom'] = $client->getNom();
            $data[$key]['prenom'] = $client->getPrenom();
            $data[$key]['email'] = $client->getMail();
        }

        return new JsonResponse($data);
    }


    /**
     * @Route("/client/new", name="client_new", methods={"GET","POST"})
     */
    public function newClient(Request $request): Response
    {
        $user = new User();
        $form = $this->createForm(UserClientType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user->setRoles(['ROLE_CLIENT']);
            $user->setPassword($this->passwordEncoder->encodePassword(
                $user,
                $user->getPassword()
            ));
            $user->setRecupass($user->getPassword());
            $user->setPresence(1);
            $user->setDateInscription($this->dateHelper->dateNow());
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($user);
            $entityManager->flush();

            return $this->redirectToRoute('client_index');
        }

        return $this->render('admin/user/newclient.html.twig', [
            'user' => $user,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/newVenteComptoir", name="newClientVenteComptoir", methods={"GET","POST"})
     */
    public function newClientVenteComptoir(Request $request, UserRepository $userRepository): Response
    {
        $nom = $request->query->get('nom');
        $prenom = $request->query->get('prenom');
        $email = $request->query->get('email');
        $telephone = $request->query->get('telephone');

        if ($nom != null && $prenom != null && $email != null && $telephone != null) {
            # code...
            //creation of new client 
            $user = new User();
            $user->setNom($nom);
            $user->setPrenom($prenom);
            $user->setMail($email);
            $user->setTelephone($telephone);
            $user->setMail($email);
            $user->setRoles(['ROLE_CLIENT']);
            $user->setPassword($this->passwordEncoder->encodePassword(
                $user,
                $nom . $telephone
            ));
            $user->setRecupass($user->getPassword());
            $user->setPresence(1);
            $user->setDateInscription(new \DateTime('NOW', new DateTimeZone('+0300')));
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($user);
            $entityManager->flush();
        }

        $data = array();

        $clients = $userRepository->findClients();

        foreach ($clients as $key => $client) {

            $data[$key]['id'] = $client->getId();
            $data[$key]['nom'] = $client->getNom();
            $data[$key]['prenom'] = $client->getPrenom();
            $data[$key]['email'] = $client->getMail();
        }

        return new JsonResponse($data);
    }



    /**
     * @Route("/client/{id}", name="client_show", methods={"GET"})
     */
    public function clientShow(User $user): Response
    {
        return $this->render('admin/user/showClient.html.twig', [
            'user' => $user,
        ]);
    }


    /**
     * @Route("/client/modifier/{id}", name="client_edit", methods={"GET","POST"})
     */
    public function editClient(Request $request, User $user): Response
    {
        $form = $this->createForm(ClientEditType::class, $user);
        $form->handleRequest($request);
        $password = $user->getPassword();
        if ($form->isSubmitted() && $form->isValid()) {
            $user->setRoles([$user->getFonction()]);
            /* if($user->getFonction() == "Client"){
                $user->setRoles([$user->getFonction()]);
            }
            if($user->getFonction() == "Employé"){
                $user->setRoles(["ROLE_PERSONNEL"]);
            }
            if($user->getFonction() == "Administrateur"){
                $user->setRoles(["ROLE_ADMIN"]);
            } */
            if ($user->getPassword() == '') {
                $user->setPassword($user->getRecupass());
            } else {
                $user->setPassword($this->passwordEncoder->encodePassword(
                    $user,
                    $user->getPassword()
                ));
                $user->setRecupass($user->getPassword());
            }
            /* if($user->getFonction == "Employé"){
                $user->setRoles("ROLE_SUPER_ADMIN");
            } */
            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('client_index');
        }

        return $this->render('admin/user/editClient.html.twig', [
            'user' => $user,
            'form' => $form->createView(),
        ]);
    }
    /**
     * @Route("/client/delete/{id}", name="client_delete", methods={"DELETE"})
     */
    public function clientDelete(Request $request, User $user): Response
    {

        if (count($user->getReservations()) == 0 && count($user->getDevis()) == 0) {

            if ($this->isCsrfTokenValid('delete' . $user->getId(), $request->request->get('_token'))) {
                $entityManager = $this->getDoctrine()->getManager();
                $entityManager->remove($user);
                $entityManager->flush();
            }
            $this->flashy->success("Le client st supprimé avec succès");
            return $this->redirectToRoute('client_index');
        } else {
            $this->flashy->error("Le client ne peut pas être supprimé car relié à des réservations ou devis");
            return $this->redirectToRoute('client_index');
        }
    }


    /**
     * @Route("/employes", name="employe_index", methods={"GET"})
     */
    public function indexEmploye(UserRepository $userRepository, Request $request): Response
    {
        $personnels = array();
        $users = $userRepository->findAll();
        foreach ($users as $user) {

            if (in_array('ROLE_PERSONNEL', $user->getRoles()) || in_array('ROLE_ADMIN', $user->getRoles()) || in_array('ROLE_SUPER_ADMIN', $user->getRoles())) {
                array_push($personnels, $user);
            }
        }

        return $this->render('admin/agence/comptes_utilisateurs/index.html.twig', [
            'personnels' => $personnels
        ]);
    }


    /**
     * @Route("/employe/new", name="employe_new", methods={"GET","POST"})
     */
    public function newEmploye(Request $request): Response
    {
        $user = new User();
        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $user->setRoles([$request->request->get('user')['fonction']]);
            $user->setPassword($this->passwordEncoder->encodePassword(
                $user,
                $user->getPassword()
            ));
            $user->setRecupass($user->getPassword());
            $user->setPresence(1);
            $user->setDateInscription(new \DateTime('now'));
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($user);
            $entityManager->flush();

            return $this->redirectToRoute('employe_index');
        }

        return $this->render('admin/agence/comptes_utilisateurs/newEmploye.html.twig', [
            'user' => $user,
            'form' => $form->createView(),
        ]);
    }


    /**
     * @Route("/employe/{id}", name="employe_show", methods={"GET"})
     */
    public function show(User $user): Response
    {
        return $this->render('admin/agence/comptes_utilisateurs/showEmploye.html.twig', [
            'user' => $user,
        ]);
    }

    /**
     * @Route("/employe/modifier/{id}", name="employe_edit", methods={"GET","POST"})
     */
    public function editEmploye(Request $request, User $user): Response
    {
        $form = $this->createForm(UserEditType::class, $user);
        $form->handleRequest($request);
        $password = $user->getPassword();
        if ($form->isSubmitted() && $form->isValid()) {
            $user->setRoles([$user->getFonction()]);
            /* if($user->getFonction() == "Client"){
                $user->setRoles([$user->getFonction()]);
            }
            if($user->getFonction() == "Employé"){
                $user->setRoles(["ROLE_PERSONNEL"]);
            }
            if($user->getFonction() == "Administrateur"){
                $user->setRoles(["ROLE_ADMIN"]);
            } */
            if ($user->getPassword() == '') {
                $user->setPassword($user->getRecupass());
            } else {
                $user->setPassword($this->passwordEncoder->encodePassword(
                    $user,
                    $user->getPassword()
                ));
                $user->setRecupass($user->getPassword());
            }
            /* if($user->getFonction == "Employé"){
                $user->setRoles("ROLE_SUPER_ADMIN");
            } */
            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('employe_index');
        }

        return $this->render('admin/agence/comptes_utilisateurs/editEmploye.html.twig', [
            'user' => $user,
            'form' => $form->createView(),
        ]);
    }


    /**
     * @Route("/employe/delete/{id}", name="employe_delete", methods={"DELETE"})
     */
    public function deleteEmploye(Request $request, User $user): Response
    {
        if ($this->isCsrfTokenValid('delete' . $user->getId(), $request->request->get('_token'))) {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($user);
            $entityManager->flush();
        }

        return $this->redirectToRoute('employe_index');
    }
}
