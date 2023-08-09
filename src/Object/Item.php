<?php

namespace RM_PagBank\Object;

class Item implements \JsonSerializable
{
    private $reference_id;
    private $name;
    private $quantity;
    private $unit_amount;

    public function jsonSerialize()
    {
        return get_object_vars($this);
    }
    
    /**
     * @return string
     */
    public function getReferenceId(): string
    {
        return $this->reference_id;
    }

    /**
     * @param string $reference_id
     */
    public function setReferenceId(string $reference_id): void
    {
        $this->reference_id = substr($reference_id, 0, 255);
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
        $this->name = substr($name, 0, 100);
    }

    /**
     * @return int
     */
    public function getQuantity(): int
    {
        return $this->quantity;
    }

    /**
     * @param int $quantity
     */
    public function setQuantity(int $quantity): void
    {
        $this->quantity = $quantity;
    }

    /**
     * @return int
     */
    public function getUnitAmount(): int
    {
        return $this->unit_amount;
    }

    /**
     * @param int $unit_amount
     */
    public function setUnitAmount(int $unit_amount): void
    {
        $this->unit_amount = $unit_amount;
    }
}