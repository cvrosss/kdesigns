<?php

namespace App\Form;

use App\Entity\Category;
use App\Entity\Product;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Image;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\PositiveOrZero;
use Symfony\Component\Validator\Constraints\Range;

class ProductType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // Product's setters are non-nullable, so a blank field must not reach them: text fields fall
        // back to '' and the others skip the setter, leaving NotBlank to report the blank.
        $builder
            ->add('name', TextType::class, ['empty_data' => '', 'attr' => ['maxlength' => 150]])
            ->add('description', TextareaType::class, ['empty_data' => '', 'attr' => ['rows' => 3]])
            ->add('category', EntityType::class, [
                'class' => Category::class,
                'choice_label' => 'categoryname',
                'placeholder' => 'Choose a category',
                'setter' => static function (Product $product, ?Category $category): void {
                    if (null !== $category) {
                        $product->setCategory($category);
                    }
                },
                'constraints' => [new NotBlank()],
            ])
            ->add('badge', TextType::class, [
                'required' => false,
                'label' => 'Badge (optional)',
                'attr' => ['maxlength' => 30, 'placeholder' => 'e.g. Best Seller'],
            ])
            ->add('price', IntegerType::class, [
                'label' => 'Price (₱)',
                'attr' => ['min' => 0],
                'setter' => static function (Product $product, ?int $price): void {
                    if (null !== $price) {
                        $product->setPrice($price);
                    }
                },
                'constraints' => [new NotBlank(), new PositiveOrZero()],
            ])
            ->add('stock', IntegerType::class, [
                'label' => $options['is_edit'] ? 'Stock' : 'Opening stock',
                'attr' => ['min' => 0, 'max' => Product::MAX_STOCK],
                'setter' => static function (Product $product, ?int $stock): void {
                    if (null !== $stock) {
                        $product->setStock($stock);
                    }
                },
                'constraints' => [new NotBlank(), new Range(min: 0, max: Product::MAX_STOCK)],
            ])
            // Not mapped: Product stores a path, which ProductImageUploader produces after validation.
            ->add('imageFile', FileType::class, [
                'mapped' => false,
                'label' => $options['is_edit'] ? 'Replace photo (optional)' : 'Photo',
                'required' => !$options['is_edit'],
                'attr' => ['accept' => 'image/jpeg,image/png,image/webp'],
                'constraints' => [
                    ...($options['is_edit'] ? [] : [new NotBlank(message: 'Please choose a photo.')]),
                    new Image(
                        maxSize: '2M',
                        mimeTypes: ['image/jpeg', 'image/png', 'image/webp'],
                        mimeTypesMessage: 'Upload a JPG, PNG or WebP image.',
                    ),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Product::class, 'is_edit' => false]);
        $resolver->setAllowedTypes('is_edit', 'bool');
    }
}
