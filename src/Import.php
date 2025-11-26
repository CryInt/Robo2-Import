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

    protected $afterAll = null;

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

    public function setAfterAll($afterAll): void
    {
        $this->afterAll = $afterAll;
    }

    public function run(): void
    {
        $startTime = microtime(true);

        $this->log('Begin [Import version: ' . $this->version . ']', 100);

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

                if ($this->limit !== null) {
                    if ($this->offset === null) {
                        $this->offset = 0;
                    }

                    $items = array_slice($items, $this->offset, $this->limit);
                }

                $items = self::makeItems($items, $worker::TYPE);

                $logString = $worker->before();
                if ($logString !== null) {
                    $this->log('Before: ' . $logString, $this->getLogCode($worker::CODE, 20));
                }

                $countProcessed = $countNew = $countUpdate = $countSkip = $countStatus = $countCallbackItems = 0;

                $callbackItems = [];

                foreach ($items as $n => $item) {
                    if ($n % 1000 === 0) {
                        $this->log('Processed ' . $type . 's [' . $n . '/' . $count . ']', $this->getLogCode($worker::CODE, 25));
                    }

                    $item = $worker->fix($item);

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

                    $itemData = $worker->getItemData($itemId);
                    if ($itemData !== null) {
                        $countCallbackItems++;
                        $callbackItems[] = $itemData->array();
                    }

                    if ($countCallbackItems >= 1000) {
                        $this->callbackItems($callbackItems, $this->getLogCode($worker::CODE, 90));
                        $countCallbackItems = 0;
                        $callbackItems = [];
                    }
                }

                if (count($callbackItems) > 0) {
                    $this->callbackItems($callbackItems, $this->getLogCode($worker::CODE, 90));
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

        if ($this->afterAll !== null) {
            $logString = ($this->afterAll)();
            if ($logString !== null) {
                $this->log('After All: ' . $logString, 997);
            }
        }

        $this->log('Time spend: ' . gmdate('H:i:s', (int)(microtime(true) - $startTime)), 998);
        $this->log('End', 999);
    }

    public function images(): void
    {
        $startTime = microtime(true);

        $this->log('Images begin [Import version: ' . $this->version . ']', 2000);

        /**
         * @var WorkerInterface $worker
         */
        foreach ($this->imports as $type => $worker) {
            if (empty($worker)) {
                continue;
            }

            $itemsWithoutImages = $worker->getItemWithoutImages($this->offset, $this->limit);
            if ($itemsWithoutImages === null) {
                continue;
            }

            $this->log('Start import images for ' . $type . 's', $this->getLogCode($worker::CODE, 2000));
            $this->log('Something from ' . $type . 's without images: ' . count($itemsWithoutImages), $this->getLogCode($worker::CODE, 2010));

            $images = $this->getImages($type);

            if (!empty($images)) {
                $count = count($images);
                $this->log('Received ' . $type . 's images: ' . $count, $this->getLogCode($worker::CODE, 2020));

                $countProcessed = $countUnavailable = $countBad = 0;

                foreach ($itemsWithoutImages as $itemsWithoutImage) {
                    if (empty($itemsWithoutImage['robo_ids'])) {
                        $countUnavailable++;
                        continue;
                    }

                    $result = null;
                    $tried = false;

                    foreach ($itemsWithoutImage['robo_ids'] as $roboId) {
                        if (array_key_exists($roboId, $images) && !empty($images[$roboId]['url'])) {
                            $tried = true;
                            $imageTmp = $this->getImageFromUrl($images[$roboId]['url']);
                            if (!empty($imageTmp)) {
                                $result = $worker->setImage($itemsWithoutImage['item_type'], $itemsWithoutImage['item_id'], $imageTmp);
                                if ($result) {
                                    $countProcessed++;
                                    break;
                                }
                            }
                        }
                    }

                    if ($tried === false) {
                        $countUnavailable++;
                    }
                    elseif (empty($result)) {
                        $countBad++;
                    }
                }

                $this->log('Image unavailable in ' . $type . 's: ' . $countUnavailable, $this->getLogCode($worker::CODE, 2030));
                $this->log('Image processed in ' . $type . 's: ' . $countProcessed, $this->getLogCode($worker::CODE, 2040));
                $this->log('Image bad in ' . $type . 's: ' . $countBad, $this->getLogCode($worker::CODE, 2050));
            }
        }

        $this->log('Time spend: ' . gmdate('H:i:s', (int)(microtime(true) - $startTime)), 2998);
        $this->log('Images end', 2999);
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

    protected function getImages(string $type): ?array
    {
        $data = $this->transport->cUrl([
            'type' => 'image',
            'part' => $type,
        ]);

        if (!empty($data) && self::isJson($data)) {
            try {
                $data = json_decode($data, true, 512, JSON_THROW_ON_ERROR);
            }
            catch (JsonException $exception) {
                echo $exception->getMessage();
                return null;
            }

            if (!empty($data['error'])) {
                $this->log('Error getting ' . ucfirst($type) .  " images: " . $data['error']);
                return null;
            }

            return $data;
        }

        return null;
    }

    protected function getImageFromUrl(string $url): ?string
    {
        if (mb_strpos($url, 'png') !== false) {
            $imageTMP = sys_get_temp_dir() . '/tmp.png';
        } else {
            $imageTMP = sys_get_temp_dir() . '/tmp.jpg';
        }

        if (file_exists($imageTMP)) {
            unlink($imageTMP);
        }

        if (mb_strpos('crm.', $url) === false) {
            @copy($url, $imageTMP);
            if (file_exists($imageTMP) && filesize($imageTMP) > 3500) {
                return $imageTMP;
            }
        }

        return null;
    }

    protected function callbackItems(array $logItems, $code = null)
    {
        $logItemsJson = json_encode($logItems);
        $this->transport->cUrl(['type' => 'logItems'], ['list' => $logItemsJson, 'code' => $code]);
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