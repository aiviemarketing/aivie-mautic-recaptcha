<?php

declare(strict_types=1);

namespace MauticPlugin\AivieRecaptchaBundle\Tests\Unit\Integration;

use MauticPlugin\AivieRecaptchaBundle\Integration\RecaptchaIntegration;
use PHPUnit\Framework\TestCase;

final class RecaptchaIntegrationTest extends TestCase
{
    public function testGetters(): void
    {
        $integration = new RecaptchaIntegration();
        $this->assertSame(RecaptchaIntegration::NAME, $integration->getName());
        $this->assertSame(RecaptchaIntegration::DISPLAY_NAME, $integration->getDisplayName());
        $this->assertSame('plugins/AivieRecaptchaBundle/Assets/img/recaptcha.png', $integration->getIcon());
    }
}
