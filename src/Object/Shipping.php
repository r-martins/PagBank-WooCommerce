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
    private string $service_type;
    private bool $address_modifiable;
    private int $amount;
    private Address $address;
//    not implemented
//    private Box $box;

    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return get_object_vars($this);
    }

	public function getServiceType(): string
	{
		return $this->service_type;
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
        return $this->amount;
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

}
