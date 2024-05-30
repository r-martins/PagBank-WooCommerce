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
class Interest implements JsonSerializable
{
    private int $total;
    private int $installments;


    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return get_object_vars($this);
    }

	public function getInstallments(): int
	{
		return $this->installments;
	}

	public function setInstallments(int $installments): void
	{
		$this->installments = $installments;
	}

	public function getTotal(): int
	{
		return $this->total;
	}

	public function setTotal(int $total): void
	{
		$this->total = $total;
	}


}
