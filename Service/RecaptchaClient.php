<?php

declare(strict_types=1);

namespace MauticPlugin\AivieRecaptchaBundle\Service;

use Google\Cloud\RecaptchaEnterprise\V1\Assessment;
use Google\Cloud\RecaptchaEnterprise\V1\Event;
use Google\Cloud\RecaptchaEnterprise\V1\RecaptchaEnterpriseServiceClient;
use Google\Cloud\RecaptchaEnterprise\V1\TokenProperties\InvalidReason;
use Mautic\CoreBundle\Helper\ArrayHelper;
use Mautic\FormBundle\Entity\Field;
use Psr\Log\LoggerInterface;

class RecaptchaClient
{
    // may only include "A-Za-z/_". Do not include user-specific information
    private const TAG_NAME = 'mautic_form';

    private string $siteKey;

    /**
     * the Google cloud project id.
     */
    private string $project;

    /**
     * FormSubscriber constructor.
     */
    public function __construct(private LoggerInterface $logger)
    {
        $this->siteKey   = getenv('GC_RECAPTCHA_SITE_KEY') ?: '';
        $this->project   = getenv('GOOGLE_CLOUD_PROJECT') ?: '';
    }

    public function getTagActionName(): string
    {
        return self::TAG_NAME;
    }

    /**
     * Check if a form submission is estimated to be from a bot.
     */
    public function verify(string $token, Field $field): bool
    {
        if (empty($token)) {
            $this->logger->error('Recaptcha: Frontend token is empty. Should not be empty. There is something wrong with the JS frontend');

            return false;
        }

        $riskScore = $this->createAssessment($this->siteKey, $token, $this->project, $this->getTagActionName());
        $minScore  = (float) ArrayHelper::getValue('minScore', $field->getProperties());
        if ($riskScore > 0 && $minScore <= $riskScore) {
            $this->logger->error('Recaptcha: valid - minimum score ('.$minScore.') is met: '.$riskScore);

            return true;
        }
        $this->logger->error('Recaptcha: risky - minimum score ('.$minScore.') is NOT met: '.$riskScore);

        return false;
    }

    /**
     * Gets the score based on the reCAPTCHA token.
     *
     * @param string $recaptchaKey reCAPTCHA key from Google cloud console
     * @param string $token        token from the frontend (recaptcha.html.twig)
     * @param string $project      Google Cloud-Project-ID
     * @param string $action       Corresponds with the $token set in recaptcha.html.php. E.g. submit or login
     */
    private function createAssessment(
        string $recaptchaKey,
        string $token,
        string $project,
        string $action
    ): float {
        $client      = new RecaptchaEnterpriseServiceClient();
        $projectName = $client->projectName($project);

        $event      = (new Event())->setSiteKey($recaptchaKey)->setToken($token);
        $assessment = (new Assessment())->setEvent($event);

        try {
            $response = $client->createAssessment(
                $projectName,
                $assessment
            );

            if (false == $response->getTokenProperties()->getValid()) {
                $this->logger->error(sprintf(
                    'Recaptcha: CreateAssessment() failed: because the token was invalid. Reason: %s',
                    InvalidReason::name($response->getTokenProperties()->getInvalidReason())
                ));

                return 0;
            }

            $tagAction = $response->getTokenProperties()->getAction();
            if ($tagAction == $action) {
                $this->logger->error('Recaptcha: The score is:'.$response->getRiskAnalysis()->getScore());

                return $response->getRiskAnalysis()->getScore();
            } else {
                $message = "Recaptcha: The action attribute in your reCAPTCHA tag ($tagAction) does not match the action you are expecting to score ($action)";
            }
        } catch (\Exception $e) {
            $message = 'Recaptcha: CreateAssessment() call failed with the following error: '.$e->getMessage();
        }

        $this->logger->error($message);

        return 0;
    }
}
