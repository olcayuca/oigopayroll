<?php

namespace Tests\Unit;

use App\Support\Text;
use PHPUnit\Framework\TestCase;

class TextTest extends TestCase
{
    public function test_organization_initials_skip_legal_suffixes(): void
    {
        $this->assertSame('OY', Text::initials('Oigo Yazılım A.Ş.'));
        $this->assertSame('OG', Text::initials('Oigo Gıda San. ve Tic. A.Ş.'));
        $this->assertSame('OL', Text::initials('Oigo Lojistik Ltd. Şti.'));
        $this->assertSame('BE', Text::initials('Beta'));
        $this->assertSame('İK', Text::initials('ilk kurum'));
        $this->assertSame('', Text::initials(null));
    }
}
