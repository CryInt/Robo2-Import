<?php
namespace Robo2Import\Workers;

use Robo2Import\Import;

class TruckWheelsWorker extends Worker
{
    const TYPE = Import::TYPE_TRUCK_WHEELS;
    const CODE = 500;
}