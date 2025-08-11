<?php
namespace Robo2Import\Items;

final class CallbackItem
{
    public ?string $robo_id = null;
    public ?string $client_item_type = null;
    public ?string $client_item_id = null;
    public ?string $client_item_ident = null;
    public ?string $quantity = null;
    public ?string $price = null;
    public ?string $presence = null;

    public function array(): array
    {
        return get_object_vars($this);
    }
}