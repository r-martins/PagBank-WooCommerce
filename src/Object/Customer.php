<?php
/** @noinspection PhpUnused */

namespace RM_PagBank\Object;

use JsonSerializable;

/**
 * Class Customer
 *
 * @author    Ricardo Martins
 * @copyright 2023 Magenteiro
 * @package   RM_PagBank\Object
 */
class Customer implements JsonSerializable
{
    private string $name;
    private string $email;
    private string $tax_id;
    private mixed $phone;

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
        $this->name = $name;
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
        $email = strtolower($email);
        $this->email = $email;
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
        $this->tax_id = $tax_id;
    }

    /**
     * @return array
     */
    public function getPhone(): mixed
    {
        return $this->phone;
    }

    /**
     * @param array|Phone $phone When in Redirect mode, it receives the phone object directly
     */
    public function setPhone(mixed $phone): void
    {
        $this->phone = $phone;
    }

}
