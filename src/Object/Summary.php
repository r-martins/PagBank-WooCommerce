<?php

namespace RM_PagSeguro\Object;

use DateTime;
use RM_PagSeguro\Helpers\Params;

class Summary implements \JsonSerializable
{
    private $total;
    private $paid;
    private $refunded;

    public function jsonSerialize()
    {
        return get_object_vars($this);
    }
    
    /**
     * @return int
     */
    public function getTotal(): int
    {
        return $this->total;
    }

    /**
     * @param int $total
     */
    public function setTotal(int $total): void
    {
        $this->total = $total;
    }

    /**
     * @return int
     */
    public function getPaid(): int
    {
        return $this->paid;
    }

    /**
     * @param int $paid
     */
    public function setPaid(int $paid): void
    {
        $this->paid = $paid;
    }

    /**
     * @return int
     */
    public function getRefunded(): int
    {
        return $this->refunded;
    }

    /**
     * @param int $refunded
     */
    public function setRefunded(int $refunded): void
    {
        $this->refunded = $refunded;
    }
    
}