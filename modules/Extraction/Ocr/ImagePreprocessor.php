<?php

declare(strict_types=1);

namespace Modules\Extraction\Ocr;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Imagick;
use ImagickPixel;
use Modules\Extraction\Exceptions\OcrException;
use Throwable;

/**
 * Thermal receipts are the worst case for OCR: low contrast, skewed, noisy.
 * Normalising first is worth more accuracy than any Tesseract tuning flag.
 */
final class ImagePreprocessor
{
    private const SUPPORTED_IMAGE_MIMES = ['image/jpeg', 'image/png', 'image/webp', 'image/heic', 'image/tiff'];

    /** @return string path of the normalized PNG on the same disk */
    public function normalize(string $disk, string $path, string $mime): string
    {
        $source = Storage::disk($disk);

        if (!$source->exists($path)) {
            throw OcrException::engineFailed("source file [{$path}] not found on disk [{$disk}]");
        }

        $target = 'normalized/'.Str::uuid()->toString().'.png';

        try {
            $image = $this->load((string) $source->get($path), $mime);

            $image->setImageColorspace(Imagick::COLORSPACE_GRAY);
            $image->deskewImage(40.0);
            $image->despeckleImage();
            $image->normalizeImage();
            $image->setImageFormat('png');
            $image->stripImage();

            $source->put($target, $image->getImageBlob());
            $image->clear();
        } catch (OcrException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw OcrException::engineFailed('preprocessing failed: '.$exception->getMessage(), $exception);
        }

        return $target;
    }

    /**
     * @throws \ImagickException
     */
    private function load(string $contents, string $mime): Imagick
    {
        if ($mime === 'application/pdf') {
            $image = new Imagick;
            $image->setResolution((int) config('extraction.ocr.pdf_dpi'), (int) config('extraction.ocr.pdf_dpi'));
            $image->readImageBlob($contents);
            // Flatten multi-page PDFs onto one canvas so OCR sees the whole document.
            $image = $image->appendImages(true);
            $image->setImageBackgroundColor(new ImagickPixel('white'));

            return $image->flattenImages();
        }

        if (!in_array($mime, self::SUPPORTED_IMAGE_MIMES, true)) {
            throw OcrException::unsupportedMime($mime);
        }

        $image = new Imagick;
        $image->readImageBlob($contents);

        return $image;
    }
}
