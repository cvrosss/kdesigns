<?php

namespace App\Security;

use App\Entity\Product;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Who may change a product: admins any product, staff only the ones they created.
 * Seeded products have no owner, so they are admin-only.
 *
 * @extends Voter<string, Product>
 */
final class ProductVoter extends Voter
{
    public const EDIT = 'PRODUCT_EDIT';
    public const DELETE = 'PRODUCT_DELETE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, [self::EDIT, self::DELETE], true) && $subject instanceof Product;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User || !$user->isActive()) {
            return false;
        }

        if (\in_array(User::ROLE_ADMIN, $user->getRoles(), true)) {
            return true;
        }

        return \in_array(User::ROLE_STAFF, $user->getRoles(), true)
            && null !== $subject->getCreatedBy()
            && $subject->getCreatedBy()->getId() === $user->getId();
    }
}
