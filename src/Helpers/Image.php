<?php
namespace Robo2Import\Helpers;

class Image
{
    protected string $processor = 'IM';

    protected array $imageMagickParameters = [
        'quality' => '90',
        'compress' => 'JPEG',
        'colorspace' => 'sRGB',
        'background' => 'white',
        'alpha' => 'off',
        'quiet' => '',
        'strip' => '',
    ];

    public function __construct(?string $processor = 'IM')
    {
        $this->processor = $processor;
    }

    public function setImageMagickParameters(array $imageMagickParameters): void
    {
        $this->imageMagickParameters = $imageMagickParameters;
    }

    public function setImageMagickParameter(string $parameter, string $value): void
    {
        $this->imageMagickParameters[$parameter] = $value;
    }

    public function convertImage($src, $dist, int $nX, int $nY): bool
    {
        if (!file_exists($src)) {
            return false;
        }

        $srcSize = getimagesize($src);

        // Если требуемое изображение меньше нужного - то просто конвертируем
        if ($nX >= $srcSize[0] && $nY >= $srcSize[1]) {
            [$newWidth, $newHeight] = $srcSize;
        }
        elseif ($nX === -1) {
            if ($nY > $srcSize[1]) {
                [$newWidth, $newHeight] = $srcSize;
            }

            if ($nY <= $srcSize[1]) {
                $newWidth  = ceil ($nY * $srcSize[0] / $srcSize[1]);
                $newHeight = $nY;
            }
        }
        elseif ($nY === -1) {
            if ($nX > $srcSize[0]) {
                [$newWidth, $newHeight] = $srcSize;
            }

            if ($nX <= $srcSize[0]) {
                $newWidth  = $nX;
                $newHeight = ceil ($nX * $srcSize[1] / $srcSize[0]);
            }
        }
        else {
            $srcRatio = $srcSize[1] / $srcSize[0];
            $newRatio = $nY / $nX;

            if ($srcRatio <= $newRatio) {
                $newWidth  = $nX;
                $newHeight = ceil($nX * $srcSize[1] / $srcSize[0]);
            }
            else {
                $newWidth  = ceil($nY * $srcSize[0] / $srcSize[1]);
                $newHeight = $nY;
            }
        }

        // Если конечное изображение было - удаляем
        if ($src !== $dist && file_exists($dist)) {
            unlink($dist);
        }

        if (empty($newWidth) || empty($newHeight)) {
            return false;
        }

        if ($this->processor === 'GD') {
            $srcGD = @imagecreatefromjpeg($src);
            $dstGD = imagecreatetruecolor($newWidth, $newHeight);
            imagecopyresampled($dstGD, $srcGD, 0, 0, 0, 0, $newWidth, $newHeight, $srcSize[0], $srcSize[1]);
            imagejpeg($dstGD, $dist, 70);
            imagedestroy($dstGD);
        }

        if ($this->processor === 'IM') {
            system("convert ".$src."[0] -resize " . $newWidth . "x" . $newHeight . " " . self::makeBashParameters($this->imageMagickParameters) . " " . $dist);
        }

        if (file_exists($dist)) {
            chmod($dist,0666);
            return true;
        }

        return false;
    }

    protected static function makeBashParameters(array $parameters): string
    {
        $result = [];

        foreach ($parameters as $key => $value) {
            $result[] = '-' . $key . (!empty($value) ? ' ' . $value : '');
        }

        return implode(' ', $result);
    }
}