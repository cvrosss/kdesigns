<?php

namespace App\Form;

use App\Entity\Category;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class CategoryType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // Category's setters are non-nullable, so a blank field falls back to '' and NotBlank reports it.
        $builder
            ->add('categoryname', TextType::class, [
                'label' => 'Category name',
                'empty_data' => '',
                'attr' => ['maxlength' => 100],
                'constraints' => [new NotBlank(message: 'Please enter a category name.'), new Length(max: 100)],
            ])
            ->add('description', TextareaType::class, [
                'empty_data' => '',
                'attr' => ['rows' => 3],
                'constraints' => [new NotBlank(message: 'Please enter a description.')],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Category::class,
        ]);
    }
}
