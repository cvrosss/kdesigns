<?php

namespace App\Entity;

use App\Repository\CategoryRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\String\Slugger\AsciiSlugger;

#[ORM\Entity(repositoryClass: CategoryRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\UniqueConstraint(name: 'UNIQ_CATEGORY_NAME', fields: ['categoryname'])]
#[UniqueEntity(fields: ['categoryname'], message: 'A category with this name already exists.')]
#[UniqueEntity(fields: ['slug'], message: 'A category with a very similar name already exists. Choose a more distinct name.')]
class Category
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100, unique: true)]
    private ?string $categoryname = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $description = null;

    /** URL-safe key used by the catalog filter (?category=fresh-bouquet). */
    #[ORM\Column(length: 100, unique: true)]
    private ?string $slug = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCategoryname(): ?string
    {
        return $this->categoryname;
    }

    public function setCategoryname(string $categoryname): static
    {
        $this->categoryname = $categoryname;

        // Derive the slug as soon as the name is known, so validation can check it for
        // collisions before the insert reaches the database. An existing category keeps its
        // slug when renamed: it is the catalog's URL key (?category=fresh-bouquet) and
        // changing it would break every link and bookmark already pointing at it.
        $this->generateSlug();

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    #[ORM\PrePersist]
    public function generateSlug(): void
    {
        if (null === $this->slug || '' === $this->slug) {
            $this->slug = strtolower((new AsciiSlugger())->slug((string) $this->categoryname)->toString());
        }
    }
}
