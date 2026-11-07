<?php

namespace App\Support;

use App\Models\Setting;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Cloudflare R2: where customers' live-photo videos are kept.
 *
 * The videos used to live as public Yandex Disk links, and a public link is
 * somebody else's allowance: the owner's Disk filled up, Yandex shut the
 * whole account's API, and six printed boxes went dark at once with the
 * files sitting safe and unreachable. A box printed last spring has to work
 * next spring, so the videos went somewhere that charges nothing for being
 * read and does not stop when a quota is reached.
 *
 * R2 speaks S3, and S3 wants its requests signed. The whole AWS library is
 * a hundred megabytes for the four calls this needs — and `vendor/` is
 * committed in this repository — so the signing is done here, in eighty
 * lines. The arithmetic is what AWS publishes test vectors for, and
 * `R2SignsItsRequestsTest` checks it against one of them.
 *
 * Nothing here is configured from a file: the owner pastes his keys into
 * «Canlı şəkillər» in the admin, they are kept encrypted, and a shop with
 * no keys simply carries on without R2.
 */
class R2
{
    private const SERVICE = 's3';

    /** R2 has one region and calls it «auto». */
    private const REGION = 'auto';

    public static function configured(): bool
    {
        return filled(self::account()) && filled(self::bucket())
            && filled(self::key()) && filled(self::secret());
    }

    public static function account(): string
    {
        return trim((string) Setting::get(Setting::R2_ACCOUNT));
    }

    public static function bucket(): string
    {
        return trim((string) Setting::get(Setting::R2_BUCKET));
    }

    public static function key(): string
    {
        return trim((string) Setting::get(Setting::R2_KEY));
    }

    /** Kept encrypted, like every other password the shop holds. */
    public static function secret(): string
    {
        $stored = (string) Setting::get(Setting::R2_SECRET);
        if ($stored === '') {
            return '';
        }

        try {
            return Crypt::decryptString($stored);
        } catch (\Throwable $e) {
            return '';
        }
    }

    /**
     * Whether what was pasted even looks like a secret access key.
     *
     * Cloudflare shows three values on one screen when a token is made,
     * and the eye goes to the first: «Token value», beginning cfat_. Paste
     * that and the key id is still accepted, so S3 answers
     * «SignatureDoesNotMatch» — which reads as a broken signature and
     * sends everybody looking in the wrong place. The shape is checked
     * before the blame is.
     *
     * Says nothing about the secret itself beyond its shape.
     */
    public static function secretLooksWrong(): ?string
    {
        $secret = self::secret();

        if ($secret === '') {
            return 'Secret Access Key boşdur.';
        }
        if (str_starts_with($secret, 'cfat_')) {
            return 'Bura «Token value» yapışdırılıb. Lazım olan aşağıdakı «Secret Access Key»dir.';
        }
        if (! preg_match('/^[0-9a-f]{64}$/i', $secret)) {
            return 'Secret Access Key 64 onaltılıq simvol olmalıdır, indi ' . strlen($secret) . ' simvoldur.';
        }

        return null;
    }

    public static function saveSecret(?string $secret): void
    {
        Setting::put(Setting::R2_SECRET, filled($secret) ? Crypt::encryptString(trim($secret)) : '');
    }

    /** Where the world reads the bucket: the r2.dev address or his own domain. */
    public static function publicBase(): string
    {
        return rtrim(trim((string) Setting::get(Setting::R2_PUBLIC)), '/');
    }

    /** The address a telephone will fetch this object from, or null. */
    public static function url(string $key): ?string
    {
        $base = self::publicBase();

        return $base === '' ? null : $base . '/' . ltrim($key, '/');
    }

    /* ----------------------------------------------------------- the calls */

    /**
     * Puts one local file in the bucket and gives back its public address.
     *
     * @throws RuntimeException with a message for the owner
     */
    public static function put(string $localPath, string $key, string $type = 'video/mp4'): string
    {
        if (! is_file($localPath)) {
            throw new RuntimeException('Fayl hostinqdə tapılmadı: ' . $localPath);
        }

        /* Streamed, not read into a string: these are telephone videos of
           fifteen megabytes and the hosting does not have memory to waste.
           The hash has to be of the whole body all the same - that is what
           S3 signs - so the file is read once by hash_file and once by the
           socket, rather than held in the middle. */
        $hash = hash_file('sha256', $localPath);
        $handle = fopen($localPath, 'rb');
        if (! $handle) {
            throw new RuntimeException('Fayl oxuna bilmədi: ' . $localPath);
        }

        /* Content-Length is deliberately not signed. The client sets it
           from the stream, and a signature that promised a header the
           request then carries differently is refused with a bare 403. */
        $stream = Utils::streamFor($handle);

        try {
            $response = self::send('PUT', $key, $hash, ['Content-Type' => $type], $stream);
        } finally {
            /* Closed here and not left to whoever read it: a handle the
               client keeps is a file the machine still holds open, and a
               command that uploads a dozen of them holds a dozen. */
            $stream->close();
        }

        if (! $response->successful()) {
            throw new RuntimeException('Cloudflare R2 faylı qəbul etmədi: ' . self::why($response));
        }

        return self::url($key) ?? '';
    }

    public static function delete(string $key): bool
    {
        return self::send('DELETE', $key, hash('sha256', ''))->successful();
    }

    /** Whether the object is really in the bucket. */
    public static function has(string $key): bool
    {
        return self::send('HEAD', $key, hash('sha256', ''))->successful();
    }

    /* --------------------------------------------------------- the signing */

    private static function send(string $method, string $key, string $payloadHash, array $extra = [], $body = null)
    {
        if (! self::configured()) {
            throw new RuntimeException('Cloudflare R2 qoşulmayıb.');
        }

        $host = self::account() . '.r2.cloudflarestorage.com';
        $path = '/' . self::bucket() . '/' . ltrim($key, '/');
        $now = now()->utc();
        $stamp = $now->format('Ymd\THis\Z');
        $day = $now->format('Ymd');

        $headers = array_merge([
            'Host' => $host,
            'x-amz-content-sha256' => $payloadHash,
            'x-amz-date' => $stamp,
        ], $extra);

        // Signed in the order S3 wants them: lower-cased names, sorted.
        $signed = array_change_key_case($headers);
        ksort($signed);
        $canonicalHeaders = '';
        foreach ($signed as $name => $value) {
            $canonicalHeaders .= $name . ':' . trim((string) $value) . "\n";
        }
        $signedNames = implode(';', array_keys($signed));

        $canonical = implode("\n", [
            $method,
            implode('/', array_map('rawurlencode', explode('/', $path))),
            '',                      // no query on any of these calls
            $canonicalHeaders,
            $signedNames,
            $payloadHash,
        ]);

        $scope = $day . '/' . self::REGION . '/' . self::SERVICE . '/aws4_request';
        $toSign = implode("\n", [
            'AWS4-HMAC-SHA256',
            $stamp,
            $scope,
            hash('sha256', $canonical),
        ]);

        $signature = self::signature(
            self::signingKey(self::secret(), $day, self::REGION, self::SERVICE),
            $toSign
        );

        $headers['Authorization'] = 'AWS4-HMAC-SHA256 '
            . 'Credential=' . self::key() . '/' . $scope . ', '
            . 'SignedHeaders=' . $signedNames . ', '
            . 'Signature=' . $signature;

        unset($headers['Host']);     // the client sets it from the address

        $request = Http::timeout(300)->withHeaders($headers);
        if ($body !== null) {
            $request = $request->withBody($body, (string) ($extra['Content-Type'] ?? 'application/octet-stream'));
        }

        return $request->send($method, 'https://' . $host . $path);
    }

    /**
     * What R2 said, in a line.
     *
     * S3 answers a refused signature and a wrong key with the same 403 and
     * tells the difference only in the XML underneath - «SignatureDoesNotMatch»
     * against «InvalidAccessKeyId» - which is the one thing worth knowing
     * when a key will not take.
     */
    private static function why($response): string
    {
        $body = (string) $response->body();
        $code = preg_match('#<Code>([^<]+)</Code>#', $body, $m) ? $m[1] : '';
        $said = preg_match('#<Message>([^<]+)</Message>#', $body, $m) ? $m[1] : '';

        return trim($response->status() . ($code !== '' ? ' ' . $code : '')
            . ($said !== '' ? ' — ' . $said : ''));
    }

    /**
     * The chain AWS derives a signing key with: date, region, service, and
     * the words «aws4_request», each one keyed by the last.
     */
    public static function signingKey(string $secret, string $day, string $region, string $service): string
    {
        $k = hash_hmac('sha256', $day, 'AWS4' . $secret, true);
        $k = hash_hmac('sha256', $region, $k, true);
        $k = hash_hmac('sha256', $service, $k, true);

        return hash_hmac('sha256', 'aws4_request', $k, true);
    }

    public static function signature(string $signingKey, string $stringToSign): string
    {
        return hash_hmac('sha256', $stringToSign, $signingKey);
    }
}
