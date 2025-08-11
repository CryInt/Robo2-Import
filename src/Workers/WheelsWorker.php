<?php
namespace Robo2Import\Workers;

use Robo2Import\Import;

class WheelsWorker extends Worker
{
    const TYPE = Import::TYPE_WHEELS;
    const CODE = 300;
}