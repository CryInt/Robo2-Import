<?php
namespace Robo2Import\Interfaces;

use Robo2Import\Items\Item;

interface WorkerInterface
{
    public function fix(Item $item): Item;
    public function before(): ?string;
    public function skip(Item $item): bool;
    public function find(Item $item): ?int;
    public function add(Item $item): ?int;
    public function update(Item $item, int $itemId): bool;
    public function status(Item $item, int $itemId): bool;
    public function after(): ?string;
}