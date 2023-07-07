<?php

namespace RM_PagSeguro\Object;

class Card implements \JsonSerializable
{
    public function jsonSerialize()
    {
        return get_object_vars($this);
    }
}