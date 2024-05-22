<?php
/** @noinspection PhpUnused */

namespace RM_PagBank\Object;

use JsonSerializable;

/**
 * Class InstructionLines
 *
 * @author    Ricardo Martins
 * @copyright 2023 Magenteiro
 * @package   RM_PagBank\Object
 */
class InstructionLines implements JsonSerializable
{
    private string $line_1;
    private string $line_2;

    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return get_object_vars($this);
    }

    /**
     * @return string
     */
    public function getLine1(): string
    {
        return $this->line_1;
    }

    /**
     * @param string $line_1
     */
    public function setLine1(string $line_1): void
    {
        $this->line_1 = substr($line_1, 0, 75);
    }

    /**
     * @return string
     */
    public function getLine2(): string
    {
        return $this->line_2;
    }

    /**
     * @param string $line_2
     */
    public function setLine2(string $line_2): void
    {
        $this->line_2 = substr($line_2, 0, 75);
    }

}
