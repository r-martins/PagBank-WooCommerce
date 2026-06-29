<?php

namespace RM_PagBank\Tests\Helpers;

use RM_PagBank\Helpers\TaxId;

/**
 * @covers \RM_PagBank\Helpers\TaxId
 */
class TaxIdTest extends \WP_UnitTestCase
{
    public function testSanitizeForApiRemovesMaskAndUppercasesCnpj()
    {
        $this->assertSame('12345678000195', TaxId::sanitizeForApi('12.345.678/0001-95'));
        $this->assertSame('AB12CD34EF5678', TaxId::sanitizeForApi('ab12.cd34/ef56-78'));
    }

    public function testSanitizeForApiPreservesCpfDigitsOnly()
    {
        $this->assertSame('12345678909', TaxId::sanitizeForApi('123.456.789-09'));
    }

    public function testIsValidFormatAcceptsCpfAndLegacyCnpj()
    {
        $this->assertTrue(TaxId::isValidFormat('123.456.789-09'));
        $this->assertTrue(TaxId::isValidFormat('12.345.678/0001-95'));
    }

    public function testIsValidFormatAcceptsAlphanumericCnpj()
    {
        $this->assertTrue(TaxId::isValidFormat('AB12CD34EF5678'));
    }

    public function testIsValidFormatRejectsInvalidLength()
    {
        $this->assertFalse(TaxId::isValidFormat('1234567890'));
        $this->assertFalse(TaxId::isValidFormat('AB12CD34EF561'));
    }

    public function testFormatForDisplayAppliesCnpjMask()
    {
        $this->assertSame('12.345.678/0001-95', TaxId::formatForDisplay('12345678000195'));
        $this->assertSame('AB.12C.D34/EF56-78', TaxId::formatForDisplay('AB12CD34EF5678'));
    }

    public function testFormatForDisplayAppliesCpfMask()
    {
        $this->assertSame('123.456.789-09', TaxId::formatForDisplay('12345678909'));
    }

    public function testIsValidCnpjAcceptsLegacyNumericCnpj()
    {
        $this->assertTrue(TaxId::isValidCnpj('11.222.333/0001-81'));
        $this->assertTrue(TaxId::isValidCnpj('11444777000161'));
    }

    public function testIsValidCnpjRejectsInvalidCheckDigits()
    {
        $this->assertFalse(TaxId::isValidCnpj('11.222.333/0001-80'));
        $this->assertFalse(TaxId::isValidCnpj('00000000000000'));
    }

    public function testIsValidCnpjAcceptsAlphanumericFormatWithValidDv()
    {
        $this->assertTrue(TaxId::isValidCnpj('1R.2PW.NPU/0001-41'));
        $this->assertTrue(TaxId::isValidCnpj('1R2PWNPU000141'));
    }

    public function testDetectType()
    {
        $this->assertSame('cpf', TaxId::detectType('12345678909'));
        $this->assertSame('cnpj', TaxId::detectType('12345678000195'));
        $this->assertSame('cnpj', TaxId::detectType('AB12CD34EF5678'));
    }
}
