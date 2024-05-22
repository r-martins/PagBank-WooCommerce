<?php
/** @noinspection PhpUnused */

namespace RM_PagBank\Object;

use JsonSerializable;

/**
 * Class Recurring
 *
 * @author    Ricardo Martins
 * @copyright 2023 Magenteiro
 * @package   RM_PagBank\Object
 */
class Recurring implements JsonSerializable
{
    protected string $type;

    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return get_object_vars($this);
    }
    
	public function getType(): string
	{
		return $this->type;
	}

	/**
	 * Set Recurring Type. Can be INITIAL or SUBSEQUENT.
	 * @param string $type
	 *
	 * @return void
	 */
	public function setType(string $type): void
	{
		$this->type = $type;
	}

}
