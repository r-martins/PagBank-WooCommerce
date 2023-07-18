<?php

namespace RM_PagSeguro\Object;

/**
 * Class QrCode
 *
 * @author    Ricardo Martins <ricardo@magenteiro.com>
 * @copyright 2023 Magenteiro
 * @package   RM_PagSeguro\Object
 */
class QrCode implements \JsonSerializable
{
    private Amount $amount;
    private string $expiration_date;

    public function jsonSerialize()
    {
        return get_object_vars($this);
    }
    
    /**
     * @return int
     */
    public function getAmount(): Amount
    {
        return $this->amount;
    }

    /**
     * @param int $amount
     */
    public function setAmount(Amount $amount): void
    {
        $this->amount = $amount;
    }

    /**
     * @return string
     */
    public function getExpirationDate(): string
    {
        return $this->expiration_date;
    }

    /**
     * @param string $expiration_date ISO 8601 (2021-08-29T20:15:59-03:00)
     */
    public function setExpirationDate(string $expiration_date): void
    {
        $this->expiration_date = $expiration_date;
    }
    
}