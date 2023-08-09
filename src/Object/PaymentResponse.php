<?php

namespace RM_PagBank\Object;

use DateTime;
use RM_PagBank\Helpers\Params;

class PaymentResponse implements \JsonSerializable
{
    protected $code;
    protected $message;
    protected $reference;

    public function jsonSerialize()
    {
        return get_object_vars($this);
    }
    
    /**
     * @return int
     */
    public function getCode(): int
    {
        return $this->code;
    }

    /**
     * @param int $code
     */
    public function setCode(int $code): void
    {
        $this->code = $code;
    }

    /**
     * @return string
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * @param string $message
     */
    public function setMessage(string $message): void
    {
        $this->message = substr($message, 0, 100);
    }

    /**
     * @return string
     */
    public function getReference(): string
    {
        return $this->reference;
    }

    /**
     * @param string $reference
     */
    public function setReference(string $reference): void
    {
        $this->reference = substr($reference, 0, 20);
    }
    
    
}