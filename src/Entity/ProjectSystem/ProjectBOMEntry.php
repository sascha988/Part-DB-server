<?php
/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 - 2022 Jan Böhmer (https://github.com/jbtronics)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace App\Entity\ProjectSystem;

use App\Entity\Base\AbstractDBElement;
use App\Entity\Base\TimestampTrait;
use App\Entity\Parts\Part;
use App\Entity\PriceInformations\Currency;
use App\Validator\Constraints\BigDecimal\BigDecimalPositive;
use App\Validator\Constraints\Selectable;
use Brick\Math\BigDecimal;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * The ProjectBOMEntry class represents an entry in a project's BOM.
 *
 * @ORM\Table("project_bom_entries")
 * @ORM\HasLifecycleCallbacks()
 * @ORM\Entity()
 * @UniqueEntity(fields={"part", "project"}, message="project.bom_entry.part_already_in_bom")
 * @UniqueEntity(fields={"name", "project"}, message="project.bom_entry.name_already_in_bom", ignoreNull=true)
 */
class ProjectBOMEntry extends AbstractDBElement
{
    use TimestampTrait;

    /**
     * @var float
     * @ORM\Column(type="float", name="quantity")
     * @Assert\Positive()
     */
    protected float $quantity;

    /**
     * @var string A comma separated list of the names, where this parts should be placed
     * @ORM\Column(type="text", name="mountnames")
     */
    protected string $mountnames;

    /**
     * @var string An optional name describing this BOM entry (useful for non-part entries)
     * @ORM\Column(type="string", nullable=true)
     * @Assert\Expression(
     *     "this.getPart() !== null or this.getName() !== null",
     *     message="validator.project.bom_entry.name_or_part_needed"
     * )
     */
    protected ?string $name = null;

    /**
     * @var string An optional comment for this BOM entry
     * @ORM\Column(type="text")
     */
    protected string $comment;

    /**
     * @var Project
     * @ORM\ManyToOne(targetEntity="Project", inversedBy="bom_entries")
     * @ORM\JoinColumn(name="id_device", referencedColumnName="id")
     */
    protected ?Project $project = null;

    /**
     * @var Part|null The part associated with this
     * @ORM\ManyToOne(targetEntity="App\Entity\Parts\Part", inversedBy="project_bom_entries")
     * @ORM\JoinColumn(name="id_part", referencedColumnName="id", nullable=true)
     */
    protected ?Part $part = null;

    /**
     * @var BigDecimal The price of this non-part BOM entry
     * @ORM\Column(type="big_decimal", precision=11, scale=5, nullable=true)
     * @Assert\AtLeastOneOf({
     *     @BigDecimalPositive(),
     *     @Assert\IsNull()
     * })
     */
    protected ?BigDecimal $price;

    /**
     * @var ?Currency The currency for the price of this non-part BOM entry
     * @ORM\ManyToOne(targetEntity="App\Entity\PriceInformations\Currency")
     * @ORM\JoinColumn(nullable=true)
     * @Selectable()
     */
    protected ?Currency $price_currency = null;

    public function __construct()
    {
        $this->price = BigDecimal::zero()->toScale(5);
    }

    /**
     * @return float
     */
    public function getQuantity(): float
    {
        return $this->quantity;
    }

    /**
     * @param  float  $quantity
     * @return ProjectBOMEntry
     */
    public function setQuantity(float $quantity): ProjectBOMEntry
    {
        $this->quantity = $quantity;
        return $this;
    }

    /**
     * @return string
     */
    public function getMountnames(): string
    {
        return $this->mountnames;
    }

    /**
     * @param  string  $mountnames
     * @return ProjectBOMEntry
     */
    public function setMountnames(string $mountnames): ProjectBOMEntry
    {
        $this->mountnames = $mountnames;
        return $this;
    }

    /**
     * @return string
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * @param  string  $name
     * @return ProjectBOMEntry
     */
    public function setName(?string $name): ProjectBOMEntry
    {
        $this->name = $name;
        return $this;
    }

    /**
     * @return string
     */
    public function getComment(): string
    {
        return $this->comment;
    }

    /**
     * @param  string  $comment
     * @return ProjectBOMEntry
     */
    public function setComment(string $comment): ProjectBOMEntry
    {
        $this->comment = $comment;
        return $this;
    }

    /**
     * @return Project|null
     */
    public function getProject(): ?Project
    {
        return $this->project;
    }

    /**
     * @param  Project|null  $project
     * @return ProjectBOMEntry
     */
    public function setProject(?Project $project): ProjectBOMEntry
    {
        $this->project = $project;
        return $this;
    }



    /**
     * @return Part|null
     */
    public function getPart(): ?Part
    {
        return $this->part;
    }

    /**
     * @param  Part|null  $part
     * @return ProjectBOMEntry
     */
    public function setPart(?Part $part): ProjectBOMEntry
    {
        $this->part = $part;
        return $this;
    }

    /**
     * Returns the price of this BOM entry, if existing.
     * Prices are only valid on non-Part BOM entries.
     * @return BigDecimal|null
     */
    public function getPrice(): ?BigDecimal
    {
        return $this->price;
    }

    /**
     * Sets the price of this BOM entry.
     * Prices are only valid on non-Part BOM entries.
     * @param  BigDecimal|null  $price
     */
    public function setPrice(?BigDecimal $price): void
    {
        $this->price = $price;
    }

    /**
     * @return Currency|null
     */
    public function getPriceCurrency(): ?Currency
    {
        return $this->price_currency;
    }

    /**
     * @param  Currency|null  $price_currency
     */
    public function setPriceCurrency(?Currency $price_currency): void
    {
        $this->price_currency = $price_currency;
    }

    /**
     * Checks whether this BOM entry is a part associated BOM entry or not.
     * @return bool True if this BOM entry is a part associated BOM entry, false otherwise.
     */
    public function isPartBomEntry(): bool
    {
        return $this->part !== null;
    }

    /**
     * @Assert\Callback
     */
    public function validate(ExecutionContextInterface $context, $payload): void
    {
        //Round quantity to whole numbers, if the part is not a decimal part
        if ($this->part) {
            if (!$this->part->getPartUnit() || $this->part->getPartUnit()->isInteger()) {
                $this->quantity = round($this->quantity);
            }
        }
        //Non-Part BOM entries are rounded
        if ($this->part === null) {
            $this->quantity = round($this->quantity);
        }

        //Check that every part name in the mountnames list is unique (per bom_entry)
        $mountnames = explode(',', $this->mountnames);
        $mountnames = array_map('trim', $mountnames);
        $uniq_mountnames = array_unique($mountnames);

        //If the number of unique names is not the same as the number of names, there are duplicates
        if (count($mountnames) !== count($uniq_mountnames)) {
            $context->buildViolation('project.bom_entry.mountnames_not_unique')
                ->atPath('mountnames')
                ->addViolation();
        }

        //Check that the number of mountnames is the same as the (rounded) quantity
        if (!empty($this->mountnames) && count($uniq_mountnames) !== (int) round ($this->quantity)) {
            $context->buildViolation('project.bom_entry.mountnames_quantity_mismatch')
                ->atPath('mountnames')
                ->addViolation();
        }

        //Prices are only allowed on non-part BOM entries
        if ($this->part !== null && $this->price !== null) {
            $context->buildViolation('project.bom_entry.price_not_allowed_on_parts')
                ->atPath('price')
                ->addViolation();
        }

        //Check that the part is not the build representation part of this device or one of its parents
        if ($this->part && $this->part->getBuiltProject() !== null) {
            //Get the associated project
            $associated_project = $this->part->getBuiltProject();
            //Check that it is not the same as the current project neither one of its parents
            $current_project = $this->project;
            while ($current_project) {
                if ($associated_project === $current_project) {
                    $context->buildViolation('project.bom_entry.can_not_add_own_builds_part')
                        ->atPath('part')
                        ->addViolation();
                }
                $current_project = $current_project->getParent();
            }
        }
    }


}
