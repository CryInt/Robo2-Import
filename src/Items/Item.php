<?php
namespace Robo2Import\Items;

use Robo2Import\Interfaces\ItemInterface;

abstract class Item implements ItemInterface
{
    public function __construct(array $item)
    {
        foreach ($item as $key => $value) {
            if (property_exists($this, $key)) {
                $this->{$key} = $value;
            }
        }
    }
}