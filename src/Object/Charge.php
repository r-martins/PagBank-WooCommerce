<?php
/** @noinspection PhpUnused */

namespace RM_PagBank\Object;

use DateTime;
use JsonSerializable;

/**
 * Class Charge
 *
 * @author    Ricardo Martins
 * @copyright 2023 Magenteiro
 * @package   RM_PagBank\Object
 */
class Charge implements JsonSerializable
{
    protected string $id;
    protected string $status;
    protected DateTime $created_at;
    protected DateTime $paid_at;
    protected string $reference_id;
    protected string $description;
    protected Amount $amount;
    protected PaymentResponse $payment_response;
    protected PaymentMethod $payment_method;
    protected Recurring $recurring;

    const ALLOWED_STATUS = [
        'AUTHORIZED',  // Indica que a cobrança está pré-autorizada.
        'PAID',        // Indica que a cobrança está paga (capturada).
        'IN_ANALYSIS', // Indica que o comprador optou por pagar com um cartão de crédito e o PagSeguro está analisando o risco da transação.
        'DECLINED',    // Indica que a cobrança foi negada pelo PagSeguro ou Emissor
        'CANCELED'     // Indica que a cobrança foi cancelada.
    ];

    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return get_object_vars($this);
    }

    /**
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @param string $id
     */
    public function setId(string $id): void
    {
        $this->id = substr($id, 0, 41);
    }

    /**
     * @return string
     */
    public function getStatus(): string
    {
        return $this->status;
    }

    /**
     * @param string $status
     */
    public function setStatus(string $status): void
    {
        $this->status = substr($status, 0, 64);
    }

    /**
     * @return DateTime
     */
    public function getCreatedAt(): DateTime
    {
        return $this->created_at;
    }

    /**
     * @param DateTime $created_at
     */
    public function setCreatedAt(DateTime $created_at): void
    {
        $this->created_at = $created_at;
    }

    /**
     * @return DateTime
     */
    public function getPaidAt(): DateTime
    {
        return $this->paid_at;
    }

    /**
     * @param DateTime $paid_at
     */
    public function setPaidAt(DateTime $paid_at): void
    {
        $this->paid_at = $paid_at;
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
        $this->reference_id = substr($reference_id, 0, 64);
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * @param string $description
     */
    public function setDescription(string $description): void
    {
        $this->description = substr($description, 0, 64);
    }

    /**
     * @return Amount
     */
    public function getAmount(): Amount
    {
        return $this->amount;
    }

    /**
     * @param Amount $amount
     */
    public function setAmount(Amount $amount): void
    {
        $this->amount = $amount;
    }

    /**
     * @return PaymentResponse
     */
    public function getPaymentResponse(): PaymentResponse
    {
        return $this->payment_response;
    }

    /**
     * @param PaymentResponse $payment_response
     */
    public function setPaymentResponse(PaymentResponse $payment_response): void
    {
        $this->payment_response = $payment_response;
    }

    /**
     * @return PaymentMethod
     */
    public function getPaymentMethod(): PaymentMethod
    {
        return $this->payment_method;
    }

    /**
     * @param PaymentMethod $payment_method
     */
    public function setPaymentMethod(PaymentMethod $payment_method): void
    {
        $this->payment_method = $payment_method;
    }

    public function getRecurring(): Recurring
    {
        return $this->recurring;
    }

    public function setRecurring(Recurring $recurring): void
    {
        $this->recurring = $recurring;
    }

}
