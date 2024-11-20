<?php

declare(strict_types=1);

namespace MauticPlugin\AivieRecaptchaBundle\Tests\Unit\Integration;

use Mautic\IntegrationsBundle\Exception\IntegrationNotFoundException;
use Mautic\IntegrationsBundle\Helper\IntegrationsHelper;
use Mautic\IntegrationsBundle\Integration\Interfaces\IntegrationInterface;
use Mautic\PluginBundle\Entity\Integration;
use MauticPlugin\AivieRecaptchaBundle\Integration\Config;
use MauticPlugin\AivieRecaptchaBundle\Integration\RecaptchaIntegration;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ConfigTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->integrationsHelper = $this->createMock(IntegrationsHelper::class);
        $this->integration        = $this->createMock(IntegrationInterface::class);
        $this->integrationEntity  = $this->createMock(Integration::class);
        $this->logger             = $this->createMock(LoggerInterface::class);

        $this->integrationsHelper
            ->method('getIntegration')
            ->with(RecaptchaIntegration::NAME)
            ->willReturn($this->integration);

        $this->integration
            ->method('getIntegrationConfiguration')
            ->willReturn($this->integrationEntity);

        $this->config = new Config($this->integrationsHelper, $this->logger);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // Remove the environment variable.
        putenv('GC_RECAPTCHA_SITE_KEY');
    }


    public function testIsPublishedThrowsException(): void
    {
        $this->integrationsHelper
            ->method('getIntegration')
            ->willThrowException(new IntegrationNotFoundException());

        $this->assertEmpty($this->config->isPublished());
    }

    /**
     * @dataProvider dataForPluginIsPublished
     */
    public function testIfPluginIsPublished(bool $setting, bool $expected): void
    {
        $this->integrationEntity
            ->method('getIsPublished')
            ->willReturn($setting);

        $this->assertSame($expected, $this->config->isPublished());
    }

    /**
     * @return iterable<string, bool[]>
     */
    public function dataForPluginIsPublished(): iterable
    {
        yield 'Published' => [true, true];

        yield 'Unpublished' => [false, false];
    }

    public function testIsConfiguredReturnsTrueWhenSiteKeyIsPresent(): void
    {
        // Simulate environment variable
        putenv('GC_RECAPTCHA_SITE_KEY=test_site_key');

        $this->assertTrue($this->config->isConfigured());
    }

    public function testIsConfiguredReturnsFalseWhenSiteKeyIsEmpty(): void
    {
        // Remove the env variable
        putenv('GC_RECAPTCHA_SITE_KEY');

        // Expect error logging
        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with('Recaptcha is not configured properly - check your ENV variables');

        $this->assertFalse($this->config->isConfigured());
    }

    public function testGetSiteKeyReturnsCorrectValue(): void
    {
        putenv('GC_RECAPTCHA_SITE_KEY=test_site_key');
        $this->assertSame('test_site_key', $this->config->getSiteKey());
    }

    public function testGetSiteKeyReturnsEmptyWhenNotSet(): void
    {
        putenv('GC_RECAPTCHA_SITE_KEY');
        $this->assertSame('', $this->config->getSiteKey());
    }
}
