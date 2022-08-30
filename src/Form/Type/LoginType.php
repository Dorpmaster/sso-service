<?php

namespace App\Form\Type;

use App\DependencyInjection\Login\LoginDto;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class LoginType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('email', EmailType::class, [
                'attr' => ['class' => 'form-control-lg', 'placeholder' => 'name@example.com'],
                'row_attr' => ['class' => 'form-outline mb-4'],
            ])
            ->add('password', PasswordType::class, [
                'attr' => ['class' => 'form-control-lg'],
                'row_attr' => ['class' => 'form-outline mb-4'],
            ])
            ->add('issuer', HiddenType::class)
            ->add('relayState', HiddenType::class)
            ->add('requestId', HiddenType::class)
            ->add('login', SubmitType::class, [
                'row_attr' =>['class' => 'd-grid gap-2'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => LoginDto::class,
        ]);
    }

}
