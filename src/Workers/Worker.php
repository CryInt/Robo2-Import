<?php
namespace Robo2Import\Workers;

use Robo2Import\Interfaces\WorkerInterface;
use Robo2Import\Items\CallbackItem;
use Robo2Import\Items\Item;

abstract class Worker implements WorkerInterface
{
    public function fix(Item $item): Item
    {
        return $item;
    }

    public function before(): ?string
    {
        return null;
    }

    public function skip(Item $item): bool
    {
        if (empty($item->robo_id)) {
            return true;
        }

        if (empty($item->price) || $item->price === '0.00') {
            return true;
        }

        if ($item->price < 900) {
            return true;
        }

        if (empty($item->quantity) || $item->quantity < 1) {
            return true;
        }

        return false;
    }

    public function find(Item $item): ?int
    {
        return null;
    }

    public function add(Item $item): ?int
    {
        return null;
    }

    public function update(Item $item, int $itemId): bool
    {
        return false;
    }

    public function status(Item $item, int $itemId): bool
    {
        return false;
    }

    public function getItemData(int $itemId): ?CallbackItem
    {
        return null;
    }

    /**
     * @return array|null [item_id,item_type,robo_ids]
     */
    public function getItemWithoutImages(): ?array
    {
        return null;
    }

    public function setImage(string $itemType, $itemId, string $imageTmp): bool
    {
        return false;
    }

    public function after(): ?string
    {
        return null;
    }
}