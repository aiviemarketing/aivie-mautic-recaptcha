<?php

declare(strict_types=1);

namespace MauticPlugin\AivieRecaptchaBundle\Integration;

use Mautic\IntegrationsBundle\Exception\IntegrationNotFoundException;
use Mautic\IntegrationsBundle\Helper\IntegrationsHelper;
use Mautic\PluginBundle\Entity\Integration;
use Psr\Log\LoggerInterface;

final class Config implements ConfigInterface
{
    public function __construct(
        private IntegrationsHelper $integrationsHelper,
        private LoggerInterface $logger
    ) {
    }

    /**
     * @throws IntegrationNotFoundException
     */
    private function getIntegrationEntity(): Integration
    {
        $integrationObject = $this->integrationsHelper->getIntegration(RecaptchaIntegration::NAME);

        return $integrationObject->getIntegrationConfiguration();
    }

    public function isConfigured(): bool
    {
        if (empty($this->getSiteKey())) {
            $this->logger->error('Recaptcha is not configured properly - check your ENV variables');

            return false;
        }

        return true;
    }

    public function isPublished(): bool
    {
        try {
            $integration = $this->getIntegrationEntity();

            return (bool) $integration->getIsPublished();
        } catch (IntegrationNotFoundException $e) {
            return false;
        }
    }

    public function getSiteKey(): string
    {
        $siteKey = getenv('GC_RECAPTCHA_SITE_KEY') ?: ($_ENV['GC_RECAPTCHA_SITE_KEY'] ?? null);
        if (empty($siteKey)) {
            return '';
        }

        return $siteKey;
    }
}
