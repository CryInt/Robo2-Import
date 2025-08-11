<?php
namespace Robo2Import\Workers;

use Robo2Import\Import;

class TruckTiresWorker extends Worker
{
    const TYPE = Import::TYPE_TRUCK_TIRES;
    const CODE = 400;
}