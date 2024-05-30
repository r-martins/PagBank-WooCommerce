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
class Buyer implements JsonSerializable
{
    private Interest $interest;


    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return get_object_vars($this);
    }

	public function getInterest(): Interest
	{
		return $this->interest;
	}

	public function setInterest(Interest $interest): void
	{
		$this->interest = $interest;
	}


}
