<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * JSON endpoints behind the Log In / Sign Up modal. The requests are same-origin
 * `application/json` posts, which browsers will not send cross-site without a CORS preflight.
 */
final class AuthController extends AbstractController
{
    #[Route('/register', name: 'app_register', methods: ['POST'])]
    public function register(
        Request $request,
        UserRepository $users,
        UserPasswordHasherInterface $hasher,
        EntityManagerInterface $em,
        ValidatorInterface $validator,
        Security $security,
    ): JsonResponse {
        if (!str_starts_with((string) $request->headers->get('Content-Type'), 'application/json')) {
            return $this->json(['error' => 'Unsupported request.'], 415);
        }

        $data = $request->toArray();
        $name = trim((string) ($data['name'] ?? ''));
        $email = mb_strtolower(trim((string) ($data['email'] ?? '')));
        $password = (string) ($data['password'] ?? '');

        $violations = $validator->validate(
            ['name' => $name, 'email' => $email, 'password' => $password],
            new Assert\Collection([
                'name' => [new Assert\NotBlank(message: 'Please fill in all fields.'), new Assert\Length(max: 80, maxMessage: 'Your name is too long.')],
                'email' => [new Assert\NotBlank(message: 'Please fill in all fields.'), new Assert\Email(message: 'Please enter a valid email address.'), new Assert\Length(max: 180)],
                'password' => [new Assert\NotBlank(message: 'Please fill in all fields.'), new Assert\Length(min: 6, max: 72, minMessage: 'Password must be at least 6 characters.', maxMessage: 'Password is too long.')],
            ]),
        );

        if (\count($violations) > 0) {
            return $this->json(['error' => $violations[0]->getMessage()], 422);
        }

        if (null !== $users->findOneBy(['email' => $email])) {
            return $this->json(['error' => 'An account with this email already exists. Try logging in.'], 409);
        }

        $user = (new User())->setName($name)->setEmail($email);
        $user->setPassword($hasher->hashPassword($user, $password));
        $em->persist($user);
        $em->flush();

        $security->login($user, 'security.authenticator.json_login.main', 'main');

        return $this->json(['name' => $user->getName()], 201);
    }

    /**
     * The json_login authenticator verifies the credentials before this runs; on success it falls through here.
     */
    #[Route('/login', name: 'app_login', methods: ['POST'])]
    public function login(#[CurrentUser] ?User $user): JsonResponse
    {
        if (null === $user) {
            return $this->json(['error' => 'Invalid credentials.'], 401);
        }

        return $this->json(['name' => $user->getName()]);
    }

    #[Route('/logout', name: 'app_logout', methods: ['GET'])]
    public function logout(): never
    {
        throw new \LogicException('This route is intercepted by the logout key on the firewall.');
    }
}
