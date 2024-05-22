<?php
/** @noinspection PhpUnused */

namespace RM_PagBank\Object;

use JsonSerializable;

/**
 * Class Boleto
 *
 * @author    Ricardo Martins
 * @copyright 2023 Magenteiro
 * @package   RM_PagBank\Object
 */
class Boleto implements JsonSerializable
{
    private string $due_date;
    private InstructionLines $instruction_lines;
    private Holder $holder;

    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return get_object_vars($this);
    }

    /**
     * @return string
     */
    public function getDueDate(): string
    {
        return $this->due_date;
    }

    /**
     * @param string $due_date yyyy-MM-dd
     */
    public function setDueDate(string $due_date): void
    {
        $this->due_date = $due_date;
    }

    /**
     * @return InstructionLines
     */
    public function getInstructionLines(): InstructionLines
    {
        return $this->instruction_lines;
    }

    /**
     * @param InstructionLines $instruction_lines
     */
    public function setInstructionLines(InstructionLines $instruction_lines): void
    {
        $this->instruction_lines = $instruction_lines;
    }

    /**
     * @return Holder
     */
    public function getHolder(): Holder
    {
        return $this->holder;
    }

    /**
     * @param Holder $holder
     */
    public function setHolder(Holder $holder): void
    {
        $this->holder = $holder;
    }

}
