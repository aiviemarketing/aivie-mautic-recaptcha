<?php

declare(strict_types=1);

namespace MauticPlugin\AivieRecaptchaBundle\Tests\Unit;

use Mautic\FormBundle\Entity\Field;
use MauticPlugin\AivieRecaptchaBundle\Service\RecaptchaClient;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class RecaptchaClientTest extends TestCase
{
    private Field $field;

    protected function setUp(): void
    {
        parent::setUp();

        $this->field = new Field();
    }

    public function testVerifyWhenPluginIsNotInstalled()
    {
        $test = $this->createRecaptchaClient()->verify('', $this->field);
        $this->assertFalse($test);
    }

    private function createRecaptchaClient(): RecaptchaClient
    {
        return new RecaptchaClient($this->createMock(LoggerInterface::class));
    }
}
