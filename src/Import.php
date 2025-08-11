<?php
namespace Robo2Import;

use JsonException;
use Robo2Import\Helpers\Transport;
use Robo2Import\Interfaces\LoggerInterface;
use Robo2Import\Interfaces\WorkerInterface;
use Robo2Import\Items\MotoTireItem;
use Robo2Import\Items\QuadroTireItem;
use Robo2Import\Items\SpecialTireItem;
use Robo2Import\Items\TireItem;
use Robo2Import\Items\TruckTireItem;
use Robo2Import\Items\TruckWheelItem;
use Robo2Import\Items\WheelItem;
use RuntimeException;

class Import
{
    protected const COMPOSER_FILE = __DIR__ . '/../composer.json';

    protected ?LoggerInterface $logger;
    protected Transport $transport;

    public const TYPE_TIRES = 'tire';
    public const TYPE_WHEELS = 'disk';
    public const TYPE_TRUCK_TIRES = 'truckTire';
    public const TYPE_TRUCK_WHEELS = 'truckDisk';
    public const TYPE_MOTO_TIRES = 'motoTire';
    public const TYPE_QUADRO_TIRES = 'quadroTire';
    public const TYPE_SPECIAL_TIRES = 'specialTire';

    protected ?int $limit = null;
    protected ?int $offset = null;

    protected string $version;

    protected array $imports = [
        self::TYPE_TIRES => null,
        self::TYPE_WHEELS => null,
        self::TYPE_TRUCK_TIRES => null,
        self::TYPE_TRUCK_WHEELS => null,
        self::TYPE_QUADRO_TIRES => null,
        self::TYPE_SPECIAL_TIRES => null,
        self::TYPE_MOTO_TIRES => null,
    ];

    public function __construct(Transport $transport, ?int $limit = null, ?int $offset = null)
    {
        $this->limit = $limit;
        $this->offset = $offset;

        $this->transport = $transport;

        $this->version = $this->getClientVersion();
    }

    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function setImportWorker(WorkerInterface $worker): void
    {
        $this->imports[$worker::TYPE] = $worker;
    }

    public function run(): void
    {
        $startTime = microtime(true);

        $this->log('Begin [Import version: ' . $this->version . ']', 0);

        /**
         * @var WorkerInterface $worker
         */
        foreach ($this->imports as $type => $worker) {
            if (empty($worker)) {
                continue;
            }

            $this->log('Start import ' . $type . 's', $this->getLogCode($worker::CODE, 0));

            $items = $this->getItems($type);

            if (!empty($items)) {
                $count = count($items);
                $this->log('Received ' . $type . 's: ' . $count, $this->getLogCode($worker::CODE, 10));

                if ($this->offset !== null && $this->limit !== null) {
                    $items = array_slice($items, $this->offset, $this->limit);
                }

                $items = self::makeItems($items, $worker::TYPE);

                $logString = $worker->before();
                if ($logString !== null) {
                    $this->log('Before: ' . $logString, $this->getLogCode($worker::CODE, 20));
                }

                $countProcessed = $countNew = $countUpdate = $countSkip = $countStatus = 0;

                foreach ($items as $n => $item) {
                    if ($n % 1000 === 0) {
                        $this->log('Processed ' . $type . 's [' . $n . '/' . $count . ']', $this->getLogCode($worker::CODE, 25));
                    }

                    if ($worker->skip($item)) {
                        $countSkip++;
                        continue;
                    }

                    $countProcessed++;

                    $itemId = $worker->find($item);

                    if ($itemId === null) {
                        $itemId = $worker->add($item);
                        if ($itemId === null) {
                            continue;
                        }

                        $countNew++;
                    }
                    elseif ($worker->update($item, $itemId)) {
                        $countUpdate++;
                    }

                    if ($worker->status($item, $itemId)) {
                        $countStatus++;
                    }
                }

                $this->log('Skipped ' . $type . 's: ' . $countSkip, $this->getLogCode($worker::CODE, 30));
                $this->log('Processed ' . $type . 's: ' . $countProcessed, $this->getLogCode($worker::CODE, 40));
                $this->log('Created ' . $type . 's: ' . $countNew, $this->getLogCode($worker::CODE, 50));
                $this->log('Updated ' . $type . 's: ' . $countUpdate, $this->getLogCode($worker::CODE, 60));
                $this->log('Statuses set ' . $type . 's: ' . $countStatus, $this->getLogCode($worker::CODE, 70));
            }

            $logString = $worker->after();
            if ($logString !== null) {
                $this->log('After: ' . $logString, $this->getLogCode($worker::CODE, 80));
            }

            $this->log('Finish import ' . $type . 's', $this->getLogCode($worker::CODE, 99));
        }

        $this->log('Time spend: ' . gmdate('H:i:s', microtime(true) - $startTime), 998);
        $this->log('End', 999);
    }

    protected function getItems(string $type): ?array
    {
        $data = $this->transport->cUrl(['type' => $type]);

        if (!empty($data) && self::isJson($data)) {
            try {
                $data = json_decode($data, true, 512, JSON_THROW_ON_ERROR);
            }
            catch (JsonException $exception) {
                echo $exception->getMessage();
                return null;
            }

            if (!empty($data['error'])) {
                $this->log('Error getting ' . ucfirst($type) .  ": " . $data['error']);
                return null;
            }

            return $data;
        }

        return null;
    }

    protected function log(string $message, ?int $code = null): void
    {
        if ($this->logger === null) {
            return;
        }

        $this->logger->log($message, $code);
    }

    protected function getLogCode(?int $code, int $number): ?int
    {
        if ($code === null) {
            return null;
        }

        return $code + $number;
    }

    protected function getClientVersion(): ?string
    {
        if (file_exists(self::COMPOSER_FILE)) {
            $composerContent = file_get_contents(self::COMPOSER_FILE);
            if (self::isJson($composerContent)) {
                try {
                    $composerData = json_decode($composerContent, true, 512, JSON_THROW_ON_ERROR);
                    if (!empty($composerData['version'])) {
                        if (mb_strpos($composerData['version'], 'v', 0, 'UTF-8') !== 0) {
                            $composerData['version'] = 'v' . $composerData['version'];
                        }

                        return $composerData['version'];
                    }
                }
                catch (JsonException $exception) {

                }
            }
        }

        return null;
    }

    protected static function isJson($string): bool
    {
        if (is_array($string)) {
            return false;
        }

        if (is_object($string)) {
            return false;
        }

        if (is_null($string)) {
            return false;
        }

        $ss = preg_replace('/"(\\.|[^"\\\\])*"/', '', $string);
        if (preg_match('/[^,:{}\\[\\]0-9.\\-+Eaeflnr-u \\n\\r\\t]/', $ss) === false) {
            return true;
        }

        try {
            $json = json_decode($string, false, 512, JSON_THROW_ON_ERROR);
            return $json && $string !== $json;
        }
        catch (JsonException $exception) {
        }

        return false;
    }

    protected static function makeItems(array $items, string $type): array
    {
        $result = [];

        foreach ($items as $item) {
            if ($type === self::TYPE_TIRES) {
                $result[] = new TireItem($item);
                continue;
            }

            if ($type === self::TYPE_WHEELS) {
                $result[] = new WheelItem($item);
                continue;
            }

            if ($type === self::TYPE_TRUCK_TIRES) {
                $result[] = new TruckTireItem($item);
                continue;
            }

            if ($type === self::TYPE_TRUCK_WHEELS) {
                $result[] = new TruckWheelItem($item);
                continue;
            }

            if ($type === self::TYPE_QUADRO_TIRES) {
                $result[] = new QuadroTireItem($item);
                continue;
            }

            if ($type === self::TYPE_SPECIAL_TIRES) {
                $result[] = new SpecialTireItem($item);
                continue;
            }

            if ($type === self::TYPE_MOTO_TIRES) {
                $result[] = new MotoTireItem($item);
                continue;
            }

            throw new RuntimeException('Unknown item type: ' . $type);
        }

        return $result;
    }
}