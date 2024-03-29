<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class UserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $roles = [
            'Employé' => 'ROLE_PERSONNEL',
            'conducteur' => 'ROLE_CONDUCTEUR',
            'Administrateur' => 'ROLE_ADMIN',
            'Super administrateur' => 'ROLE_SUPER_ADMIN'
        ];
        $builder
            ->add('fonction', ChoiceType::class, [
                'choices' => $roles,
                'label' => "Rôle de l\'utilisateur",
                'required' => true,
            ])
            ->add('password', PasswordType::class)
            ->add('nom', TextType::class)
            ->add('prenom', TextType::class)
            ->add('adresse', TextType::class)
            ->add('complementAdresse', TextType::class)
            ->add('ville', TextType::class)
            ->add('mail', TextType::class)
            ->add('telephone', TextType::class)
            ->add('portable', TextType::class)
            ->add('dateNaissance', DateType::class, [
                'widget' => 'single_text',
            ])
            ->add('lieuNaissance', TextType::class)
            ->add('numeroPermis', TextType::class)
            ->add('datePermis', DateType::class, [
                'widget' => 'single_text',
            ])
            //->add('presence')
            /* ->add('date_inscription', DateTimeType::class, [
                'widget' => 'single_text',
            ]) */;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
