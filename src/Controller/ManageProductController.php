<?php

namespace App\Controller;

use App\Entity\Product;
use App\Entity\User;
use App\Form\ProductType;
use App\Repository\CategoryRepository;
use App\Repository\ProductRepository;
use App\Security\ProductVoter;
use App\Service\ProductImageUploader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Product records for staff and admins. Everyone with access can view all products; staff can only
 * edit or delete their own (ProductVoter), admins can edit or delete any.
 */
#[Route('/manage')]
#[IsGranted('ROLE_STAFF')]
final class ManageProductController extends AbstractController
{
    #[Route('', name: 'app_manage', methods: ['GET'])]
    public function home(): RedirectResponse
    {
        return $this->redirectToRoute('app_manage_products');
    }

    #[Route('/products', name: 'app_manage_products', methods: ['GET'])]
    public function index(Request $request, ProductRepository $products, CategoryRepository $categories, #[CurrentUser] User $user): Response
    {
        $term = trim($request->query->getString('q'));
        $scope = 'mine' === $request->query->getString('scope') ? 'mine' : 'all';
        $categoryId = $request->query->getInt('category') ?: null;

        return $this->render('manage/products/index.html.twig', [
            'products' => $products->searchRecords($term, 'mine' === $scope ? $user : null, $categoryId),
            'categories' => $categories->findAll(),
            'term' => $term,
            'scope' => $scope,
            'category_id' => $categoryId,
            'total' => $products->count([]),
        ]);
    }

    #[Route('/products/new', name: 'app_manage_product_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em, ProductImageUploader $uploader, ProductRepository $products, #[CurrentUser] User $user): Response
    {
        $product = new Product();
        $form = $this->createForm(ProductType::class, $product);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $product->setImage($uploader->upload($form->get('imageFile')->getData(), $product->getName()));
            $product->setSortOrder($products->nextSortOrder());
            $product->setCreatedBy($user);
            $em->persist($product);
            $em->flush();

            $this->addFlash('admin_success', sprintf('%s was added.', $product->getName()));

            return $this->redirectToRoute('app_manage_products', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('manage/products/form.html.twig', ['form' => $form, 'product' => null], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    #[Route('/products/{id}/edit', name: 'app_manage_product_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Product $product, Request $request, EntityManagerInterface $em, ProductImageUploader $uploader, ProductRepository $products): Response
    {
        $this->denyAccessUnlessGranted(ProductVoter::EDIT, $product, 'You can only edit products you created.');

        $form = $this->createForm(ProductType::class, $product, ['is_edit' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $file = $form->get('imageFile')->getData();
            $replaced = null;
            if (null !== $file) {
                $replaced = $product->getImage();
                $product->setImage($uploader->upload($file, $product->getName()));
            }
            $em->flush();

            // The old photo is deleted only after the new path is safely stored, and only
            // when no other product still uses it.
            if (null !== $replaced && $replaced !== $product->getImage() && 0 === $products->countByImage($replaced)) {
                $uploader->remove($replaced);
            }

            $this->addFlash('admin_success', sprintf('%s was updated.', $product->getName()));

            return $this->redirectToRoute('app_manage_products', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('manage/products/form.html.twig', ['form' => $form, 'product' => $product], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    #[Route('/products/{id}/delete', name: 'app_manage_product_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Product $product, Request $request, EntityManagerInterface $em, ProductImageUploader $uploader, ProductRepository $products): RedirectResponse
    {
        $this->denyAccessUnlessGranted(ProductVoter::DELETE, $product, 'You can only delete products you created.');

        if (!$this->isCsrfTokenValid('delete_product_'.$product->getId(), $request->request->getString('_token'))) {
            $this->addFlash('admin_error', 'Your session expired — please try again.');
        } else {
            $name = $product->getName();
            $image = $product->getImage();
            $em->remove($product);
            $em->flush();

            // Only once the row is gone, and only if nothing else points at the file.
            if (null !== $image && 0 === $products->countByImage($image)) {
                $uploader->remove($image);
            }

            $this->addFlash('admin_success', sprintf('%s was deleted.', $name));
        }

        return $this->redirectToRoute('app_manage_products', [], Response::HTTP_SEE_OTHER);
    }
}
