<?php

declare(strict_types=1);

namespace MauticPlugin\AivieRecaptchaBundle\Integration;

use Mautic\IntegrationsBundle\Integration\BasicIntegration;
use Mautic\IntegrationsBundle\Integration\ConfigurationTrait;
use Mautic\IntegrationsBundle\Integration\Interfaces\BasicInterface;

/**
 * Class RecaptchaIntegration.
 */
class RecaptchaIntegration extends BasicIntegration implements BasicInterface
{
    use ConfigurationTrait;

    public const NAME           = 'reCAPTCHA';
    public const DISPLAY_NAME   = 'reCAPTCHA';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDisplayName(): string
    {
        return self::DISPLAY_NAME;
    }

    public function getIcon(): string
    {
        return 'plugins/AivieRecaptchaBundle/Assets/img/recaptcha.png';
    }
}
