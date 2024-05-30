<?php
/** @noinspection PhpUnused */

namespace RM_PagBank\Object;

use JsonSerializable;

/**
 * Class Holder
 *
 * @author    Ricardo Martins
 * @copyright 2023 Magenteiro
 * @package   RM_PagBank\Object
 */
class Holder implements JsonSerializable
{
    private string $name;
    private string $tax_id;
    private string $email;
    private Address $address;

    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return get_object_vars($this);
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName(string $name): void
    {
        $this->name = substr($name, 0, 30);
    }

    /**
     * @return string
     */
    public function getTaxId(): string
    {
        return $this->tax_id;
    }

    /**
     * @param string $tax_id
     */
    public function setTaxId(string $tax_id): void
    {
        $this->tax_id = substr($tax_id, 0, 14);
    }

    /**
     * @return string
     */
    public function getEmail(): string
    {
        return $this->email;
    }

    /**
     * @param string $email
     */
    public function setEmail(string $email): void
    {
        $this->email = substr($email, 0, 255);
    }

    /**
     * @return Address
     */
    public function getAddress(): Address
    {
        return $this->address;
    }

    /**
     * @param Address $address
     */
    public function setAddress(Address $address): void
    {
        $this->address = $address;
    }

}
