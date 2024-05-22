<?php
/** @noinspection PhpUnused */

namespace RM_PagBank\Object;

use JsonSerializable;

/**
 * Class Phone
 *
 * @author    Ricardo Martins
 * @copyright 2023 Magenteiro
 * @package   RM_PagBank\Object
 */
class Phone implements JsonSerializable
{
    private int $country = 55;
    private int $area;
    private int $number;
    private string $type = 'MOBILE';

    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return get_object_vars($this);
    }

    /**
     * @return int
     */
    public function getCountry(): int
    {
        return $this->country;
    }

    /**
     * @param int $country
     */
    public function setCountry(int $country): void
    {
        $this->country = $country;
    }

    /**
     * @return int
     */
    public function getArea(): int
    {
        return $this->area;
    }

    /**
     * @param int $area
     */
    public function setArea(int $area): void
    {
        $this->area = $area;
    }

    /**
     * @return int
     */
    public function getNumber(): int
    {
        return $this->number;
    }

    /**
     * @param int $number
     */
    public function setNumber(int $number): void
    {
        $this->number = $number;
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
	 * Type can be MOBILE, BUSINESS or HOME
     * @param string $type
     */
    public function setType(string $type): void
    {
        $this->type = $type;
    }

}
