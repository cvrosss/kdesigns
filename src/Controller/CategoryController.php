<?php

namespace App\Controller;

use App\Entity\Category;
use App\Form\CategoryType;
use App\Repository\CategoryRepository;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Category management, open to staff and admins (security.yaml access_control and #[IsGranted]).
 */
#[Route('/category')]
#[IsGranted('ROLE_STAFF')]
final class CategoryController extends AbstractController
{
    #[Route(name: 'app_category_index', methods: ['GET'])]
    public function index(CategoryRepository $categoryRepository): Response
    {
        return $this->render('category/index.html.twig', [
            'categories' => $categoryRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_category_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $category = new Category();
        $form = $this->createForm(CategoryType::class, $category);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $entityManager->persist($category);
                $entityManager->flush();
                $this->addFlash('admin_success', sprintf('Category "%s" was created.', $category->getCategoryname()));

                return $this->redirectToRoute('app_category_index', [], Response::HTTP_SEE_OTHER);
            } catch (UniqueConstraintViolationException) {
                // Validation already rejects duplicate names; this catches the race where two
                // admins save the same name at once, so they see a message instead of a 500.
                $this->addFlash('admin_error', 'That category name is already taken. Please choose another.');
            }
        }

        return $this->render('category/new.html.twig', [
            'category' => $category,
            'form' => $form,
        ], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    #[Route('/{id}', name: 'app_category_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Category $category): Response
    {
        return $this->render('category/show.html.twig', [
            'category' => $category,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_category_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, Category $category, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(CategoryType::class, $category);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $entityManager->flush();
                $this->addFlash('admin_success', sprintf('Category "%s" was updated.', $category->getCategoryname()));

                return $this->redirectToRoute('app_category_index', [], Response::HTTP_SEE_OTHER);
            } catch (UniqueConstraintViolationException) {
                $this->addFlash('admin_error', 'That category name is already taken. Please choose another.');
            }
        }

        return $this->render('category/edit.html.twig', [
            'category' => $category,
            'form' => $form,
        ], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    #[Route('/{id}', name: 'app_category_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, Category $category, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('delete'.$category->getId(), $request->getPayload()->getString('_token'))) {
            $this->addFlash('admin_error', 'Your session expired — please try again.');
        } else {
            $name = $category->getCategoryname();
            try {
                $entityManager->remove($category);
                $entityManager->flush();
                $this->addFlash('admin_success', sprintf('Category "%s" was deleted.', $name));
            } catch (ForeignKeyConstraintViolationException) {
                $this->addFlash('admin_error', sprintf('"%s" still has products. Move or delete them first.', $name));
            }
        }

        return $this->redirectToRoute('app_category_index', [], Response::HTTP_SEE_OTHER);
    }
}
