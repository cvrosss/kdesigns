<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Security\Core\Validator\Constraints\UserPassword;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class ChangePasswordType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('currentPassword', PasswordType::class, [
                'label' => 'Current password',
                'attr' => ['autocomplete' => 'current-password'],
                'constraints' => [
                    new NotBlank(message: 'Please enter your current password.'),
                    new UserPassword(message: 'That is not your current password.'),
                ],
            ])
            ->add('newPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'first_options' => ['label' => 'New password', 'attr' => ['autocomplete' => 'new-password']],
                'second_options' => ['label' => 'Repeat new password', 'attr' => ['autocomplete' => 'new-password']],
                'invalid_message' => 'The two passwords do not match.',
                'constraints' => [
                    new NotBlank(message: 'Please enter a new password.'),
                    new Length(min: 8, max: 72, minMessage: 'Password must be at least {{ limit }} characters.', maxMessage: 'Password is too long.'),
                ],
            ])
        ;
    }
}
