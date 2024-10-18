<?php

declare(strict_types=1);

namespace MauticPlugin\AivieRecaptchaBundle\EventListener;

use Mautic\CoreBundle\Exception\BadConfigurationException;
use Mautic\FormBundle\Event\FormBuilderEvent;
use Mautic\FormBundle\Event\ValidationEvent;
use Mautic\FormBundle\FormEvents;
use Mautic\LeadBundle\Event\LeadEvent;
use Mautic\LeadBundle\LeadEvents;
use Mautic\LeadBundle\Model\LeadModel;
use MauticPlugin\AivieRecaptchaBundle\Form\Type\RecaptchaType;
use MauticPlugin\AivieRecaptchaBundle\Integration\ConfigInterface;
use MauticPlugin\AivieRecaptchaBundle\RecaptchaEvents;
use MauticPlugin\AivieRecaptchaBundle\Service\RecaptchaClient;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class FormSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
        private ConfigInterface $config,
        private RecaptchaClient $recaptchaClient,
        private LeadModel $leadModel,
        private TranslatorInterface $translator
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents(): array
    {
        return [
            FormEvents::FORM_ON_BUILD         => ['onFormBuild', 0],
            RecaptchaEvents::ON_FORM_VALIDATE => ['onFormValidate', 0],
        ];
    }

    /**
     * @throws BadConfigurationException
     */
    public function onFormBuild(FormBuilderEvent $event): void
    {
        if (!$this->config->isPublished() || !$this->config->isConfigured()) {
            return;
        }

        $event->addFormField('plugin.recaptcha', [
            'label'          => 'mautic.plugin.actions.recaptcha',
            'formType'       => RecaptchaType::class,
            'template'       => '@MauticRecaptcha/Field/recaptcha.html.twig',
            'builderOptions' => [
                'addLeadFieldList' => false,
                'addIsRequired'    => false,
                'addDefaultValue'  => false,
                'addSaveResult'    => true,
            ],
            'site_key' => $this->config->getSiteKey(),
            'tagAction'=> $this->recaptchaClient->getTagActionName(),
        ]);

        $event->addValidator('plugin.recaptcha.validator', [
            'eventName' => RecaptchaEvents::ON_FORM_VALIDATE,
            'fieldType' => 'plugin.recaptcha',
        ]);
    }

    public function onFormValidate(ValidationEvent $event): void
    {
        if (!$this->config->isPublished() || !$this->config->isConfigured()) {
            return;
        }

        if ($this->recaptchaClient->verify($event->getValue(), $event->getField())) {
            return;
        }

        $event->failedValidation(null === $this->translator ? 'reCAPTCHA was not successful.' : $this->translator->trans('mautic.integration.recaptcha.failure_message'));

        $this->eventDispatcher->addListener(LeadEvents::LEAD_POST_SAVE, function (LeadEvent $event) {
            if ($event->isNew()) {
                $this->leadModel->deleteEntity($event->getLead());
            }
        }, -255);
    }
}
