<?php
declare(strict_types=1);

/**
 * WebAuthnService — dependency-free (composer-মুক্ত) WebAuthn যাচাই।
 * ────────────────────────────────────────────────────────────────
 *  • CBOR ডিকোডার (attestationObject + COSE key)
 *  • COSE → PEM (ES256 / P-256 ও RS256 / RSA সাপোর্ট)
 *  • openssl_verify দিয়ে অ্যাসারশন সিগনেচার যাচাই
 *  শুধুমাত্র PHP কোর + ext-openssl লাগে। কোনো লাইব্রেরি নয়।
 */
final class WebAuthnService
{
    private string $rpId;
    /** @var string[] */
    private array $origins;

    /** @param string[] $origins */
    public function __construct(string $rpId, array $origins)
    {
        $this->rpId    = $rpId;
        $this->origins = $origins;
    }

    /* ─────────── base64url ─────────── */
    public static function b64uEnc(string $bin): string
    {
        return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
    }
    public static function b64uDec(string $s): string
    {
        $s   = strtr($s, '-_', '+/');
        $pad = strlen($s) % 4;
        if ($pad) { $s .= str_repeat('=', 4 - $pad); }
        return (string) base64_decode($s, true);
    }
    public static function newChallenge(): string
    {
        return self::b64uEnc(random_bytes(32));
    }

    /* ─────────── CBOR decode ─────────── */
    private function cbor(string $d, int &$o)
    {
        $ib = ord($d[$o++]);
        $mt = $ib >> 5;
        $ai = $ib & 0x1f;
        $val = $this->cborLen($d, $o, $ai);
        switch ($mt) {
            case 0: return $val;          // unsigned int
            case 1: return -1 - $val;     // negative int
            case 2:                        // byte string
            case 3:                        // text string
                $s = substr($d, $o, $val); $o += $val; return $s;
            case 4:                        // array
                $a = [];
                for ($i = 0; $i < $val; $i++) { $a[] = $this->cbor($d, $o); }
                return $a;
            case 5:                        // map
                $m = [];
                for ($i = 0; $i < $val; $i++) {
                    $k = $this->cbor($d, $o);
                    $v = $this->cbor($d, $o);
                    $m[is_int($k) ? $k : (string) $k] = $v;
                }
                return $m;
            case 7: return $val;           // simple / float (rare here)
        }
        return null;
    }
    private function cborLen(string $d, int &$o, int $ai): int
    {
        if ($ai < 24)   { return $ai; }
        if ($ai === 24) { return ord($d[$o++]); }
        if ($ai === 25) { $v = unpack('n', substr($d, $o, 2))[1]; $o += 2; return (int) $v; }
        if ($ai === 26) { $v = unpack('N', substr($d, $o, 4))[1]; $o += 4; return (int) $v; }
        if ($ai === 27) {
            $hi = unpack('N', substr($d, $o, 4))[1];
            $lo = unpack('N', substr($d, $o + 4, 4))[1];
            $o += 8;
            return (int) ($hi * 4294967296 + $lo);
        }
        return 0;
    }

    /* ─────────── authenticatorData parse ─────────── */
    private function parseAuthData(string $ad): array
    {
        $res = [
            'rpIdHash'  => substr($ad, 0, 32),
            'flags'     => ord($ad[32]),
            'signCount' => (int) unpack('N', substr($ad, 33, 4))[1],
        ];
        $off = 37;
        if ($res['flags'] & 0x40) {                 // AT — attested credential data
            $off += 16;                              // aaguid
            $credLen = (int) unpack('n', substr($ad, $off, 2))[1];
            $off += 2;
            $res['credId'] = substr($ad, $off, $credLen);
            $off += $credLen;
            $o = $off;
            $res['cose'] = $this->cbor($ad, $o);
        }
        return $res;
    }

    /* ─────────── COSE key → PEM ─────────── */
    private function coseToPem(array $cose): ?string
    {
        $kty = $cose[1] ?? null;
        if ($kty === 2) {                            // EC2 / P-256 (ES256)
            $x = $cose[-2] ?? ''; $y = $cose[-3] ?? '';
            if (strlen($x) !== 32 || strlen($y) !== 32) { return null; }
            $der = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200')
                 . "\x04" . $x . $y;
            return $this->derToPem($der);
        }
        if ($kty === 3) {                            // RSA (RS256)
            $n = $cose[-1] ?? ''; $e = $cose[-2] ?? '';
            if ($n === '' || $e === '') { return null; }
            return $this->derToPem($this->rsaSpki($n, $e));
        }
        return null;
    }
    private function derToPem(string $der): string
    {
        return "-----BEGIN PUBLIC KEY-----\n"
             . chunk_split(base64_encode($der), 64, "\n")
             . "-----END PUBLIC KEY-----\n";
    }
    private function der(int $tag, string $val): string
    {
        $len = strlen($val);
        if ($len < 128) { $l = chr($len); }
        else { $b = ltrim(pack('N', $len), "\x00"); $l = chr(0x80 | strlen($b)) . $b; }
        return chr($tag) . $l . $val;
    }
    private function derUint(string $bin): string
    {
        $bin = ltrim($bin, "\x00");
        if ($bin === '') { $bin = "\x00"; }
        if (ord($bin[0]) & 0x80) { $bin = "\x00" . $bin; }
        return $this->der(0x02, $bin);
    }
    private function rsaSpki(string $n, string $e): string
    {
        $rsaPub = $this->der(0x30, $this->derUint($n) . $this->derUint($e));
        $algId  = $this->der(0x30, hex2bin('06092a864886f70d0101010500'));
        $bitStr = $this->der(0x03, "\x00" . $rsaPub);
        return $this->der(0x30, $algId . $bitStr);
    }

    /* ─────────── REGISTRATION verify ─────────── */
    public function verifyRegistration(string $clientDataJSON, string $attestationObject, string $expectedChallenge): array
    {
        $cd = json_decode($clientDataJSON, true);
        if (!is_array($cd))                                       { throw new \RuntimeException('clientData পার্স এরর'); }
        if (($cd['type'] ?? '') !== 'webauthn.create')            { throw new \RuntimeException('ভুল টাইপ'); }
        if (!hash_equals($expectedChallenge, (string)($cd['challenge'] ?? ''))) { throw new \RuntimeException('challenge মেলেনি'); }
        if (!in_array($cd['origin'] ?? '', $this->origins, true)) { throw new \RuntimeException('origin মেলেনি'); }

        $o   = 0;
        $att = $this->cbor($attestationObject, $o);
        if (!is_array($att) || empty($att['authData'])) { throw new \RuntimeException('attestation পার্স এরর'); }
        $p = $this->parseAuthData((string) $att['authData']);

        if (!hash_equals(hash('sha256', $this->rpId, true), $p['rpIdHash'])) { throw new \RuntimeException('rpId মেলেনি'); }
        if (!($p['flags'] & 0x01))                                            { throw new \RuntimeException('ইউজার উপস্থিত নয়'); }
        if (empty($p['credId']) || empty($p['cose']))                         { throw new \RuntimeException('ক্রেডেনশিয়াল নেই'); }

        $pem = $this->coseToPem($p['cose']);
        if ($pem === null) { throw new \RuntimeException('অসমর্থিত কি-টাইপ'); }

        return [
            'credentialId' => self::b64uEnc($p['credId']),
            'publicKey'    => $pem,
            'signCount'    => $p['signCount'],
        ];
    }

    /* ─────────── AUTHENTICATION verify ─────────── */
    public function verifyAuthentication(
        string $clientDataJSON,
        string $authenticatorData,
        string $signature,
        string $publicKeyPem,
        string $expectedChallenge
    ): int {
        $cd = json_decode($clientDataJSON, true);
        if (!is_array($cd))                                       { throw new \RuntimeException('clientData পার্স এরর'); }
        if (($cd['type'] ?? '') !== 'webauthn.get')               { throw new \RuntimeException('ভুল টাইপ'); }
        if (!hash_equals($expectedChallenge, (string)($cd['challenge'] ?? ''))) { throw new \RuntimeException('challenge মেলেনি'); }
        if (!in_array($cd['origin'] ?? '', $this->origins, true)) { throw new \RuntimeException('origin মেলেনি'); }

        $p = $this->parseAuthData($authenticatorData);
        if (!hash_equals(hash('sha256', $this->rpId, true), $p['rpIdHash'])) { throw new \RuntimeException('rpId মেলেনি'); }
        if (!($p['flags'] & 0x01))                                            { throw new \RuntimeException('ইউজার উপস্থিত নয়'); }

        $signedData = $authenticatorData . hash('sha256', $clientDataJSON, true);
        $ok = openssl_verify($signedData, $signature, $publicKeyPem, OPENSSL_ALGO_SHA256);
        if ($ok !== 1) { throw new \RuntimeException('সিগনেচার অবৈধ'); }

        return $p['signCount'];
    }
}
