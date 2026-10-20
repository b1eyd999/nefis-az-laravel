<?php

namespace Tests\Feature;

use App\Support\ImageStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The owner reported it twice in one month: «моя фотография растянута».
 *
 * A telephone does not turn the picture it takes. It writes the pixels
 * sideways and a flag beside them saying which way up the picture goes, and
 * everything that handles the photograph afterwards has to agree about that
 * flag. Two places did not:
 *
 *   · the browser shrank the photograph to the size written in the file's
 *     own header — the sideways size — while the browser itself had already
 *     turned it, so a tall picture was pressed into a wide frame and the
 *     face came out half again too wide. That one is in photo-shrink.js and
 *     cannot be reached from here; it is checked by hand against a file
 *     made the way a telephone makes one.
 *
 *   · the shop then stored the picture WITHOUT turning it, because GD
 *     ignores the flag. The customer placed his face upright on the screen
 *     and the box was printed with it lying on its side.
 *
 * This is the second one.
 */
class APhotoKeepsItsShapeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A JPEG written the way a telephone writes one: landscape pixels and a
     * flag that says to turn them.
     */
    private function photoFromAPhone(int $w, int $h, int $orientation): UploadedFile
    {
        $im = imagecreatetruecolor($w, $h);
        imagefill($im, 0, 0, imagecolorallocate($im, 240, 235, 225));
        imagefilledellipse($im, (int) ($w / 2), (int) ($h / 2), (int) ($h / 2), (int) ($h / 2),
            imagecolorallocate($im, 200, 90, 40));
        ob_start();
        imagejpeg($im, null, 90);
        $jpeg = (string) ob_get_clean();
        imagedestroy($im);

        /* The Exif block, by hand: a TIFF header in Intel order with one
           directory holding one tag — 0x0112, the orientation. */
        $tiff = "II\x2A\x00" . pack('V', 8)
            . pack('v', 1)
            . pack('v', 0x0112) . pack('v', 3) . pack('V', 1) . pack('v', $orientation) . "\x00\x00"
            . pack('V', 0);
        $app1 = "Exif\0\0" . $tiff;
        $segment = "\xFF\xE1" . pack('n', strlen($app1) + 2) . $app1;

        // Straight after SOI, where a camera puts it.
        $withExif = substr($jpeg, 0, 2) . $segment . substr($jpeg, 2);

        $path = tempnam(sys_get_temp_dir(), 'phone') . '.jpg';
        file_put_contents($path, $withExif);

        return new UploadedFile($path, 'IMG_0001.jpg', 'image/jpeg', null, true);
    }

    public function test_the_flag_is_read_without_the_exif_extension(): void
    {
        foreach ([1, 3, 6, 8] as $flag) {
            $file = $this->photoFromAPhone(60, 40, $flag);
            $this->assertSame($flag, ImageStore::orientation($file->getRealPath()),
                'a photograph marked ' . $flag);
            @unlink($file->getRealPath());
        }
    }

    public function test_a_photograph_is_stored_the_way_its_owner_sees_it(): void
    {
        // Marked 6: the pixels lie on their side, and it is a tall photograph.
        $file = $this->photoFromAPhone(400, 300, 6);

        [$path, $w, $h] = ImageStore::store($file, 'zz-test', 'probe', 82, 3000);

        $this->assertSame([300, 400], [$w, $h], 'turned upright, as the customer placed it');
        $this->assertGreaterThan($w, $h, 'a tall photograph stays tall');

        Storage::disk('public')->delete($path);
        @unlink($file->getRealPath());
    }

    public function test_a_photograph_with_no_flag_is_left_exactly_as_it_is(): void
    {
        $file = $this->photoFromAPhone(400, 300, 1);

        [$path, $w, $h] = ImageStore::store($file, 'zz-test', 'probe', 82, 3000);

        $this->assertSame([400, 300], [$w, $h], 'nothing to turn, so nothing is turned');

        Storage::disk('public')->delete($path);
        @unlink($file->getRealPath());
    }

    /** Upside down is a quarter turn twice, not a quarter turn once. */
    public function test_a_photograph_taken_upside_down_keeps_its_shape(): void
    {
        $file = $this->photoFromAPhone(400, 300, 3);

        [$path, $w, $h] = ImageStore::store($file, 'zz-test', 'probe', 82, 3000);

        $this->assertSame([400, 300], [$w, $h], 'still wide, just the other way up');

        Storage::disk('public')->delete($path);
        @unlink($file->getRealPath());
    }
}
