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
class Amount implements JsonSerializable
{
    private int $value;
    private string $currency = 'BRL';
    private Summary $summary;
	private Fees $fees;

    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return get_object_vars($this);
    }

    /**
     * @return int
     */
    public function getValue(): int
    {
        return $this->value;
    }

    /**
     * @param int $value
     */
    public function setValue(int $value): void
    {
        $this->value = $value;
    }

    /**
     * @return string
     */
    public function getCurrency(): string
    {
        return $this->currency;
    }

    /**
     * @param string $currency
     */
    public function setCurrency(string $currency): void
    {
        $this->currency = substr($currency, 0, 3);
    }

    /**
     * @return Summary
     */
    public function getSummary(): Summary
    {
        return $this->summary;
    }

    /**
     * @param Summary $summary
     */
    public function setSummary(Summary $summary): void
    {
        $this->summary = $summary;
    }

	public function getFees(): Fees
	{
		return $this->fees;
	}

	public function setFees(Fees $fees): void
	{
		$this->fees = $fees;
	}

}
