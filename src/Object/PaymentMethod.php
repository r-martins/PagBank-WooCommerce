<?php
/** @noinspection PhpUnused */

namespace RM_PagBank\Object;

use JsonSerializable;

/**
 * Class PaymentMethod
 *
 * @author    Ricardo Martins
 * @copyright 2023 Magenteiro
 * @package   RM_PagBank\Object
 */
class PaymentMethod implements JsonSerializable
{
    private string $type;
    private int $installments;
    private bool $capture;
    private string $soft_descriptor;
    private Card $card;
    private Boleto $boleto;
    private AuthenticationMethod $authentication_method;

    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return get_object_vars($this);
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @param string $type
     */
    public function setType(string $type): void
    {
        $this->type = $type;
    }

    /**
     * @return int
     */
    public function getInstallments(): int
    {
        return $this->installments;
    }

    /**
     * @param int $installments
     */
    public function setInstallments(int $installments): void
    {
        $this->installments = $installments;
    }

    /**
     * @return bool
     */
    public function isCapture(): bool
    {
        return $this->capture;
    }

    /**
     * @param bool $capture
     */
    public function setCapture(bool $capture): void
    {
        $this->capture = $capture;
    }

    /**
     * @return string
     */
    public function getSoftDescriptor(): string
    {
        return $this->soft_descriptor;
    }

    /**
     * @param string $soft_descriptor
     */
    public function setSoftDescriptor(string $soft_descriptor): void
    {
        $this->soft_descriptor = $soft_descriptor;
    }

    /**
     * @return Card
     */
    public function getCard(): Card
    {
        return $this->card;
    }

    /**
     * @param Card $card
     */
    public function setCard(Card $card): void
    {
        $this->card = $card;
    }

    /**
     * @return Boleto
     */
    public function getBoleto(): Boleto
    {
        return $this->boleto;
    }

    /**
     * @param Boleto $boleto
     */
    public function setBoleto(Boleto $boleto): void
    {
        $this->boleto = $boleto;
    }

    public function getAuthenticationMethod(): AuthenticationMethod
    {
        return $this->authentication_method;
    }

    public function setAuthenticationMethod(AuthenticationMethod $authentication_method): void
    {
        $this->authentication_method = $authentication_method;
    }

}
