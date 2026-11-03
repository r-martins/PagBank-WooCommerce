<?php
/** @noinspection PhpUnused */

namespace RM_PagBank\Object;

use JsonSerializable;

/**
 * Class Shipping
 *
 * @author    Ricardo Martins
 * @copyright 2025 PagBank Integrações (Parceiro Oficial)
 * @package   RM_PagBank\Object
 * @link https://developer.pagbank.com.br/reference/criar-checkout
 */
class Shipping implements JsonSerializable
{
    const TYPE_FREE = 'FREE';
    const TYPE_FIXED = 'FIXED';
    const TYPE_CALCULATE = 'CALCULATE';

    const SERVICE_TYPE_PAC = 'PAC';
    const SERVICE_TYPE_SEDEX = 'SEDEX';

    private string $type;
    private ?string $service_type = null;
    private bool $address_modifiable = true;
    private ?int $amount = null;
    private ?Address $address = null;
    private ?string $origin_postal_code = null;
    /** @var list<array<string, mixed>>|null */
    private ?array $boxes = null;
    private ?int $estimated_delivery_time_in_days = null;

    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        $out = [
            'type' => $this->type,
            'address_modifiable' => $this->address_modifiable,
        ];
        if ($this->service_type !== null) {
            $out['service_type'] = $this->service_type;
        }
        if ($this->amount !== null) {
            $out['amount'] = $this->amount;
        }
        if ($this->address !== null) {
            $out['address'] = $this->address;
        }
        if ($this->origin_postal_code !== null) {
            $out['origin_postal_code'] = $this->origin_postal_code;
        }
        if ($this->boxes !== null) {
            $out['boxes'] = $this->boxes;
        }
        if ($this->estimated_delivery_time_in_days !== null) {
            $out['estimated_delivery_time_in_days'] = $this->estimated_delivery_time_in_days;
        }
        return $out;
    }

    public function getServiceType(): string
    {
        return $this->service_type ?? '';
    }

    public function setServiceType(string $service_type): void
    {
        $this->service_type = $service_type;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    public function isAddressModifiable(): bool
    {
        return $this->address_modifiable;
    }

    public function setAddressModifiable(bool $address_modifiable): void
    {
        $this->address_modifiable = $address_modifiable;
    }

    public function getAmount(): int
    {
        return $this->amount ?? 0;
    }

    public function setAmount(int $amount): void
    {
        $this->amount = $amount;
    }

    public function getAddress(): Address
    {
        return $this->address;
    }

    public function setAddress(Address $address): void
    {
        $this->address = $address;
    }

    public function setOriginPostalCode(string $cep): void
    {
        $this->origin_postal_code = $cep;
    }

    /**
     * @param list<array<string, mixed>> $boxes
     */
    public function setBoxes(array $boxes): void
    {
        $this->boxes = $boxes;
    }
}
