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
    private int $payment_profile_id;
    /** @var array<string, mixed> */
    private array $three_ds;
    private bool $discard_credit_card;
    private int $customer_id;

    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        $data = get_object_vars($this);
        if (!isset($this->payment_profile_id)) {
            unset($data['payment_profile_id']);
        }
        if (!isset($this->three_ds)) {
            unset($data['three_ds']);
        }
        if (!isset($this->discard_credit_card)) {
            unset($data['discard_credit_card']);
        }
        if (!isset($this->customer_id)) {
            unset($data['customer_id']);
        }
        if (!isset($this->authentication_method)) {
            unset($data['authentication_method']);
        }
        if (!isset($this->boleto)) {
            unset($data['boleto']);
        }
        if (!isset($this->card)) {
            unset($data['card']);
        }
        if (!isset($this->soft_descriptor)) {
            unset($data['soft_descriptor']);
        }

        return $data;
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

    public function getPaymentProfileId(): int
    {
        return $this->payment_profile_id;
    }

    public function setPaymentProfileId(int $payment_profile_id): void
    {
        $this->payment_profile_id = $payment_profile_id;
    }

    /** @return array<string, mixed> */
    public function getThreeDs(): array
    {
        return $this->three_ds;
    }

    /** @param array<string, mixed> $three_ds */
    public function setThreeDs(array $three_ds): void
    {
        $this->three_ds = $three_ds;
    }

    public function setDiscardCreditCard(bool $discard): void
    {
        $this->discard_credit_card = $discard;
    }

    public function getCustomerId(): int
    {
        return $this->customer_id;
    }

    public function setCustomerId(int $customer_id): void
    {
        $this->customer_id = $customer_id;
    }

}
