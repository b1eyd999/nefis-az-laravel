<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * What a customer sends for a live photo (AR): the video, the picture it
 * plays over and the camera's tracking data, made from that picture in the
 * customer's own browser. Kept on the hosting only until the order is placed
 * and the video is handed to Yandex Disk.
 */
class LiveMaterials
{
    /** The hosting takes files up to 20 MB and 30 MB a request, the box's photos included. */
    public const VIDEO_MB = 18;

    public static function enabled(): bool
    {
        return Setting::get(Setting::AR_ENABLED) === '1';
    }

    public static function price(): float
    {
        return round((float) Setting::get(Setting::AR_PRICE), 2);
    }

    /**
     * Bought on its own, the customer gives the picture; with a box, the
     * picture is the box's own design, drawn by the page.
     */
    public static function rules(bool $photoRequired): array
    {
        return [
            'ar_video' => ['required', 'file', 'mimetypes:video/mp4,video/quicktime,video/webm,video/x-m4v', 'max:' . self::VIDEO_MB * 1024],
            'ar_photo' => [$photoRequired ? 'required' : 'nullable', 'image', 'max:10240'],
            'ar_mind' => ['nullable', 'file', 'max:20480'],
        ];
    }

    public static function messages(): array
    {
        return [
            'ar_video.required' => 'Canlı şəkil üçün videonu yükləyin.',
            'ar_video.mimetypes' => 'Video MP4, MOV və ya WEBM olmalıdır.',
            'ar_video.max' => 'Video ' . self::VIDEO_MB . ' MB-dan böyük ola bilməz — qısaldın və ya sıxın.',
            'ar_video.uploaded' => 'Video yüklənmədi — ' . self::VIDEO_MB . ' MB-dan kiçik olmalıdır.',
            'ar_photo.required' => 'Canlanacaq şəkli yükləyin.',
            'ar_photo.image' => 'Şəkil JPG, PNG və ya WEBP olmalıdır.',
            'ar_photo.max' => 'Şəkil 10 MB-dan böyük ola bilməz.',
        ];
    }

    /** @return array{video: string, image: ?string, mind: ?string, price: float} */
    public static function fromRequest(Request $request): array
    {
        $mind = $request->file('ar_mind');

        return [
            'video' => $request->file('ar_video')->store('cart-videos', 'public'),
            'image' => $request->file('ar_photo')?->store('cart-live', 'public'),
            // Anything that is not MindAR's data is dropped; the owner can still prepare it by hand.
            'mind' => $mind && self::isMind((string) $mind->getRealPath())
                ? $mind->storeAs('cart-live', Str::lower(Str::random(20)) . '.mind', 'public')
                : null,
            'price' => self::price(),
        ];
    }

    /** MindAR's compiled data: a MessagePack map that opens with its format version, 2. */
    public static function isMind(string $path): bool
    {
        return is_file($path) && @file_get_contents($path, false, null, 0, 4) === "\x82\xa1v\x02";
    }
}
