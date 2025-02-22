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

namespace App\Entity\Parts;

use App\Entity\Attachments\MeasurementUnitAttachment;
use App\Entity\Base\AbstractPartsContainingDBElement;
use App\Entity\Parameters\MeasurementUnitParameter;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * This unit represents the unit in which the amount of parts in stock are measured.
 * This could be something like N, grams, meters, etc...
 *
 * @ORM\Entity(repositoryClass="App\Repository\Parts\MeasurementUnitRepository")
 * @ORM\Table(name="`measurement_units`", indexes={
 *     @ORM\Index(name="unit_idx_name", columns={"name"}),
 *     @ORM\Index(name="unit_idx_parent_name", columns={"parent_id", "name"}),
 * })
 * @UniqueEntity("unit")
 */
class MeasurementUnit extends AbstractPartsContainingDBElement
{
    /**
     * @var string The unit symbol that should be used for the Unit. This could be something like "", g (for grams)
     *             or m (for meters).
     * @ORM\Column(type="string", name="unit", nullable=true)
     * @Assert\Length(max=10)
     */
    protected ?string $unit = null;

    /**
     * @var bool Determines if the amount value associated with this unit should be treated as integer.
     *           Set to false, to measure continuous sizes likes masses or lengths.
     * @ORM\Column(type="boolean", name="is_integer")
     */
    protected bool $is_integer = false;

    /**
     * @var bool Determines if the unit can be used with SI Prefixes (kilo, giga, milli, etc.).
     *           Useful for sizes like meters. For this the unit must be set
     * @ORM\Column(type="boolean", name="use_si_prefix")
     * @Assert\Expression("this.isUseSIPrefix() == false or this.getUnit() != null", message="validator.measurement_unit.use_si_prefix_needs_unit")
     */
    protected bool $use_si_prefix = false;

    /**
     * @ORM\OneToMany(targetEntity="MeasurementUnit", mappedBy="parent", cascade={"persist"})
     * @ORM\OrderBy({"name" = "ASC"})
     * @var Collection
     */
    protected $children;

    /**
     * @ORM\ManyToOne(targetEntity="MeasurementUnit", inversedBy="children")
     * @ORM\JoinColumn(name="parent_id", referencedColumnName="id")
     */
    protected $parent;

    /**
     * @var Collection<int, MeasurementUnitAttachment>
     * @ORM\OneToMany(targetEntity="App\Entity\Attachments\MeasurementUnitAttachment", mappedBy="element", cascade={"persist", "remove"}, orphanRemoval=true)
     * @ORM\OrderBy({"name" = "ASC"})
     * @Assert\Valid()
     */
    protected $attachments;

    /** @var Collection<int, MeasurementUnitParameter>
     * @ORM\OneToMany(targetEntity="App\Entity\Parameters\MeasurementUnitParameter", mappedBy="element", cascade={"persist", "remove"}, orphanRemoval=true)
     * @ORM\OrderBy({"group" = "ASC" ,"name" = "ASC"})
     * @Assert\Valid()
     */
    protected $parameters;

    /**
     * @return string
     */
    public function getUnit(): ?string
    {
        return $this->unit;
    }

    /**
     * @param  string|null  $unit
     *
     * @return MeasurementUnit
     */
    public function setUnit(?string $unit): self
    {
        $this->unit = $unit;

        return $this;
    }

    public function isInteger(): bool
    {
        return $this->is_integer;
    }

    /**
     * @return MeasurementUnit
     */
    public function setIsInteger(bool $isInteger): self
    {
        $this->is_integer = $isInteger;

        return $this;
    }

    public function isUseSIPrefix(): bool
    {
        return $this->use_si_prefix;
    }

    /**
     * @return MeasurementUnit
     */
    public function setUseSIPrefix(bool $usesSIPrefixes): self
    {
        $this->use_si_prefix = $usesSIPrefixes;

        return $this;
    }
}
