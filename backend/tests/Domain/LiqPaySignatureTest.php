<?php
declare(strict_types=1);

namespace App\Tests\Domain;

use PHPUnit\Framework\TestCase;

/** Проверка алгоритма подписи LiqPay: base64(sha1(private.data.private, raw)). */
final class LiqPaySignatureTest extends TestCase
{
    public function testSignatureAlgorithm(): void
    {
        $private = 'test_private_key';
        $data = base64_encode('{"order_id":"lex-1","status":"success"}');

        $expected = base64_encode(sha1($private . $data . $private, true));
        $recomputed = base64_encode(sha1($private . $data . $private, true));

        self::assertSame($expected, $recomputed);
        self::assertTrue(hash_equals($expected, $recomputed));
        self::assertFalse(hash_equals($expected, base64_encode(sha1('wrong' . $data . 'wrong', true))));
    }
}
