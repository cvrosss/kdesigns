<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Admin form for creating and editing accounts. On edit the password is optional (blank = unchanged).
 */
class UserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isEdit = $options['is_edit'];

        $builder
            ->add('name', TextType::class, ['empty_data' => '', 'attr' => ['maxlength' => 80]])
            ->add('email', EmailType::class, ['empty_data' => '', 'attr' => ['maxlength' => 180]])
            ->add('primaryRole', ChoiceType::class, [
                'label' => 'Role',
                'choices' => User::ROLE_CHOICES,
                'disabled' => $options['lock_account'],
                'help' => $options['lock_account'] ? 'You cannot change your own role.' : null,
            ])
            ->add('plainPassword', PasswordType::class, [
                'mapped' => false,
                'required' => !$isEdit,
                'label' => $isEdit ? 'Reset password (optional)' : 'Password',
                'attr' => ['autocomplete' => 'new-password'],
                'help' => $isEdit ? 'Leave blank to keep the current password.' : null,
                'constraints' => [
                    ...($isEdit ? [] : [new NotBlank(message: 'Please enter a password.')]),
                    new Length(min: 8, max: 72, minMessage: 'Password must be at least {{ limit }} characters.', maxMessage: 'Password is too long.'),
                ],
            ])
            ->add('active', CheckboxType::class, [
                'required' => false,
                'label' => 'Account is active',
                'disabled' => $options['lock_account'],
                'help' => $options['lock_account'] ? 'You cannot disable your own account.' : 'Disabled accounts cannot log in.',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => User::class, 'is_edit' => false, 'lock_account' => false]);
        $resolver->setAllowedTypes('is_edit', 'bool');
        $resolver->setAllowedTypes('lock_account', 'bool');
    }
}
