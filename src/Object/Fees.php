<?php
/** @noinspection PhpUnused */

namespace RM_PagBank\Object;

use JsonSerializable;

/**
 * Class Amount
 *
 * @author    Ricardo Martins
 * @copyright 2023 Magenteiro
 * @package   RM_PagBank\Object
 */
class Fees implements JsonSerializable
{
    private Buyer $buyer;


    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return get_object_vars($this);
    }

	public function getBuyer(): Buyer
	{
		return $this->buyer;
	}

	public function setBuyer(Buyer $buyer): void
	{
		$this->buyer = $buyer;
	}

}
