<?php

namespace Tests\Unit;

use App\Support\Text;
use App\Support\TurkishIdentifiers;
use PHPUnit\Framework\TestCase;

class TurkishIdentifiersTest extends TestCase
{
    public function test_tckn_checksum(): void
    {
        $this->assertTrue(TurkishIdentifiers::isValidTckn('10000000146'));
        $this->assertFalse(TurkishIdentifiers::isValidTckn('10000000147'));
        $this->assertFalse(TurkishIdentifiers::isValidTckn('01234567890'));
        $this->assertFalse(TurkishIdentifiers::isValidTckn('1000000014'));
        $this->assertTrue(TurkishIdentifiers::isValidTckn(TurkishIdentifiers::makeTckn('123456789')));
    }

    public function test_vkn_checksum(): void
    {
        $vkn = TurkishIdentifiers::makeVkn('123456789');

        $this->assertTrue(TurkishIdentifiers::isValidVkn($vkn));
        $this->assertFalse(TurkishIdentifiers::isValidVkn(substr($vkn, 0, 9).(((int) $vkn[9] + 1) % 10)));
        $this->assertFalse(TurkishIdentifiers::isValidVkn('12345'));
        $this->assertFalse(TurkishIdentifiers::isValidVkn('12345678ab'));
    }

    public function test_vkn_accepts_leading_zero(): void
    {
        $this->assertTrue(TurkishIdentifiers::isValidVkn(TurkishIdentifiers::makeVkn('012345678')));
    }

    public function test_text_key_normalises_turkish_headers(): void
    {
        $this->assertSame('isyerinumarasi', Text::key('İşyeri Numarası *'));
        $this->assertSame('isyerinumarasi', Text::key('İŞ YERİ NUMARASI'));
        $this->assertSame('igdir', Text::key('IĞDIR'));
        $this->assertSame(Text::key('Istanbul'), Text::key('İstanbul'));
    }
}
