<?php
/** @noinspection PhpUnused */

namespace RM_PagBank\Object;

use JsonSerializable;

class Item implements JsonSerializable
{
    private string $reference_id;
    private string $name;
    private int $quantity;
    private int $unit_amount;
    private ?string $image_url = null;
    private ?int $width = null;
    private ?int $length = null;
    private ?int $depth = null;
    private ?int $weight = null;

    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        $out = [
            'reference_id' => $this->reference_id,
            'name' => $this->name,
            'quantity' => $this->quantity,
            'unit_amount' => $this->unit_amount,
        ];
        if ($this->image_url !== null && $this->image_url !== '') {
            $out['image_url'] = $this->image_url;
        }
        if ($this->width !== null) {
            $out['width'] = $this->width;
        }
        if ($this->length !== null) {
            $out['length'] = $this->length;
        }
        if ($this->depth !== null) {
            $out['depth'] = $this->depth;
        }
        if ($this->weight !== null) {
            $out['weight'] = $this->weight;
        }
        return $out;
    }

    public function getReferenceId(): string
    {
        return $this->reference_id;
    }

    public function setReferenceId(string $reference_id): void
    {
        $this->reference_id = substr($reference_id, 0, 255);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = substr($name, 0, 100);
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): void
    {
        $this->quantity = $quantity;
    }

    public function getUnitAmount(): int
    {
        return $this->unit_amount;
    }

    public function setUnitAmount(int $unit_amount): void
    {
        $this->unit_amount = $unit_amount;
    }

    public function getImageUrl(): ?string
    {
        return $this->image_url;
    }

    public function setImageUrl(?string $image_url): void
    {
        $this->image_url = $image_url;
    }

    public function setWidth(?int $width): void
    {
        $this->width = $width;
    }

    public function setLength(?int $length): void
    {
        $this->length = $length;
    }

    public function setDepth(?int $depth): void
    {
        $this->depth = $depth;
    }

    public function setWeight(?int $weight): void
    {
        $this->weight = $weight;
    }
}
