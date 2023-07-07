<?php

namespace RM_PagSeguro\Object;

class Amount implements \JsonSerializable
{
    private $value;
    private $currency = 'BRL';
    private $summary;

    public function jsonSerialize()
    {
        return get_object_vars($this);
    }
    
    /**
     * @return int
     */
    public function getValue(): int
    {
        return $this->value;
    }

    /**
     * @param int $value
     */
    public function setValue(int $value): void
    {
        $this->value = $value;
    }

    /**
     * @return string
     */
    public function getCurrency(): string
    {
        return $this->currency;
    }

    /**
     * @param string $currency
     */
    public function setCurrency(string $currency): void
    {
        $this->currency = substr($currency, 0, 3);
    }

    /**
     * @return Summary
     */
    public function getSummary(): Summary
    {
        return $this->summary;
    }

    /**
     * @param Summary $summary
     */
    public function setSummary(Summary $summary): void
    {
        $this->summary = $summary;
    }
    
}