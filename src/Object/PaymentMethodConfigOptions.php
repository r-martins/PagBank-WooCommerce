<?php
/** @noinspection PhpUnused */

namespace RM_PagBank\Object;

use JsonSerializable;

/**
 * Class PaymentMethodConfigOptions
 *
 * @author    Ricardo Martins
 * @copyright 2023 Magenteiro
 * @package   RM_PagBank\Object
 * @link https://developer.pagbank.com.br/reference/criar-checkout
 */
class PaymentMethodConfigOptions implements JsonSerializable
{
    private string $option;
    private string $value;
    
    const OPTION_INSTALLMENTS_LIMIT = 'INSTALLMENTS_LIMIT';
    const OPTION_INTEREST_FREE_INSTALLMENTS = 'INTEREST_FREE_INSTALLMENTS';


    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return get_object_vars($this);
    }

    public function getOption(): string
    {
        return $this->option;
    }

    public function setOption(string $option): void
    {
        $this->option = $option;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function setValue(string $value): void
    {
        $this->value = $value;
    }


}
