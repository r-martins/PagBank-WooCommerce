<?php

namespace RM_PagBank\Object;

use JsonSerializable;

/**
 * Class Split
 * 
 * Represents the split payment structure for PagBank API
 *
 * @author    Ricardo Martins
 * @copyright 2023 Magenteiro
 * @package   RM_PagBank\Object
 */
class Split implements JsonSerializable
{
    protected string $method;
    protected array $receivers;

    const METHOD_PERCENTAGE = 'PERCENTAGE';
    const METHOD_FIXED = 'FIXED';

    public function __construct()
    {
        $this->receivers = [];
    }

    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return [
            'method' => $this->method,
            'receivers' => $this->receivers
        ];
    }

    /**
     * @return string
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * @param string $method
     */
    public function setMethod(string $method): void
    {
        $this->method = $method;
    }

    /**
     * @return array
     */
    public function getReceivers(): array
    {
        return $this->receivers;
    }

    /**
     * @param array $receivers
     */
    public function setReceivers(array $receivers): void
    {
        $this->receivers = $receivers;
    }

    /**
     * Add a receiver to the split
     *
     * @param Receiver $receiver
     */
    public function addReceiver(Receiver $receiver): void
    {
        $this->receivers[] = $receiver;
    }
}




