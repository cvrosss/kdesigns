<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\UserType;
use App\Repository\OrderRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * User management: admins create, edit, disable and delete accounts of every role.
 */
#[Route('/admin/users')]
#[IsGranted('ROLE_ADMIN')]
final class UserController extends AbstractController
{
    #[Route('', name: 'app_user_index', methods: ['GET'])]
    public function index(Request $request, UserRepository $users): Response
    {
        $term = trim($request->query->getString('q'));
        $role = $request->query->getString('role');
        $role = \in_array($role, User::ROLE_CHOICES, true) ? $role : null;

        return $this->render('admin/users/index.html.twig', [
            'users' => $users->search($term, $role),
            'term' => $term,
            'role' => $role,
            'role_choices' => User::ROLE_CHOICES,
            'total' => $users->countAll(),
        ]);
    }

    #[Route('/new', name: 'app_user_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $hasher): Response
    {
        $user = new User();
        $user->setPrimaryRole(User::ROLE_STAFF);
        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user->setPassword($hasher->hashPassword($user, (string) $form->get('plainPassword')->getData()));
            $em->persist($user);
            $em->flush();

            $this->addFlash('admin_success', sprintf('Account for %s was created.', $user->getName()));

            return $this->redirectToRoute('app_user_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/users/form.html.twig', ['form' => $form, 'user' => null], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    #[Route('/{id}/edit', name: 'app_user_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(User $user, Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $hasher, #[CurrentUser] User $me): Response
    {
        $form = $this->createForm(UserType::class, $user, ['is_edit' => true, 'lock_account' => $user->getId() === $me->getId()]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plain = (string) $form->get('plainPassword')->getData();
            if ('' !== $plain) {
                $user->setPassword($hasher->hashPassword($user, $plain));
            }
            $em->flush();

            $this->addFlash('admin_success', sprintf('Account for %s was updated.', $user->getName()));

            return $this->redirectToRoute('app_user_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/users/form.html.twig', ['form' => $form, 'user' => $user], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    #[Route('/{id}/toggle', name: 'app_user_toggle', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function toggle(User $user, Request $request, EntityManagerInterface $em, #[CurrentUser] User $me): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('toggle_user_'.$user->getId(), $request->request->getString('_token'))) {
            $this->addFlash('admin_error', 'Your session expired — please try again.');
        } elseif ($user->getId() === $me->getId()) {
            $this->addFlash('admin_error', 'You cannot disable your own account.');
        } else {
            $user->setActive(!$user->isActive());
            $em->flush();
            $this->addFlash('admin_success', sprintf('%s is now %s.', $user->getName(), $user->isActive() ? 'active' : 'disabled'));
        }

        return $this->redirectToRoute('app_user_index', array_filter(['q' => $request->query->getString('q'), 'role' => $request->query->getString('role')]), Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/delete', name: 'app_user_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(User $user, Request $request, EntityManagerInterface $em, OrderRepository $orders, #[CurrentUser] User $me): RedirectResponse
    {
        // The order rows reference the buyer with ON DELETE CASCADE, so deleting an account that
        // has ordered would take its sales history — and the shop's revenue figures — with it.
        // Disabling keeps the records and still locks the person out.
        $orderCount = $orders->countForUser($user);

        if (!$this->isCsrfTokenValid('delete_user_'.$user->getId(), $request->request->getString('_token'))) {
            $this->addFlash('admin_error', 'Your session expired — please try again.');
        } elseif ($user->getId() === $me->getId()) {
            $this->addFlash('admin_error', 'You cannot delete your own account.');
        } elseif ($orderCount > 0) {
            $this->addFlash('admin_error', sprintf(
                '%s has %d order%s on record, so the account cannot be deleted without losing that sales history. Disable the account instead — the person can no longer log in, and the orders are kept.',
                $user->getName(),
                $orderCount,
                1 === $orderCount ? '' : 's',
            ));
        } else {
            $name = $user->getName();
            $em->remove($user);
            $em->flush();
            $this->addFlash('admin_success', sprintf('Account for %s was deleted.', $name));
        }

        return $this->redirectToRoute('app_user_index', [], Response::HTTP_SEE_OTHER);
    }
}
