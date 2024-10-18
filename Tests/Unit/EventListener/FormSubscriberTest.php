<?php

declare(strict_types=1);

namespace MauticPlugin\AivieRecaptchaBundle\Tests\Unit\EventListener;

use Mautic\FormBundle\Entity\Field;
use Mautic\FormBundle\Event\FormBuilderEvent;
use Mautic\FormBundle\Event\ValidationEvent;
use Mautic\FormBundle\FormEvents;
use Mautic\LeadBundle\Event\LeadEvent;
use Mautic\LeadBundle\LeadEvents;
use Mautic\LeadBundle\Model\LeadModel;
use MauticPlugin\AivieRecaptchaBundle\EventListener\FormSubscriber;
use MauticPlugin\AivieRecaptchaBundle\Form\Type\RecaptchaType;
use MauticPlugin\AivieRecaptchaBundle\Integration\ConfigInterface;
use MauticPlugin\AivieRecaptchaBundle\RecaptchaEvents;
use MauticPlugin\AivieRecaptchaBundle\Service\RecaptchaClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class FormSubscriberTest extends TestCase
{
    private $eventDispatcher;
    private $config;
    private $recaptchaClient;
    private $leadModel;
    private $translator;
    private FormSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->config          = $this->createMock(ConfigInterface::class);
        $this->recaptchaClient = $this->createMock(RecaptchaClient::class);
        $this->leadModel       = $this->createMock(LeadModel::class);
        $this->translator      = $this->createMock(TranslatorInterface::class);

        $this->subscriber = new FormSubscriber(
            $this->eventDispatcher,
            $this->config,
            $this->recaptchaClient,
            $this->leadModel,
            $this->translator
        );
    }

    public function testSubscribedEvents(): void
    {
        $this->assertEquals(
            [
                FormEvents::FORM_ON_BUILD         => ['onFormBuild', 0],
                RecaptchaEvents::ON_FORM_VALIDATE => ['onFormValidate', 0],
            ],
            FormSubscriber::getSubscribedEvents()
        );
    }

    public function testOnFormBuildDoesNotAddFieldWhenNotConfigured(): void
    {
        $event = $this->createMock(FormBuilderEvent::class);

        $this->config->method('isPublished')->willReturn(false);
        $this->config->method('isConfigured')->willReturn(false);

        $event->expects($this->never())->method('addFormField');
        $event->expects($this->never())->method('addValidator');

        $this->subscriber->onFormBuild($event);
    }

    public function testOnFormBuildAddsRecaptchaFieldWhenConfigured(): void
    {
        $event = $this->createMock(FormBuilderEvent::class);

        $this->config->method('isPublished')->willReturn(true);
        $this->config->method('isConfigured')->willReturn(true);
        $this->config->method('getSiteKey')->willReturn('test_site_key');
        $this->recaptchaClient->method('getTagActionName')->willReturn('test_tag_action');

        $event->expects($this->once())
            ->method('addFormField')
            ->with('plugin.recaptcha', $this->callback(function ($options) {
                return $options['formType'] === RecaptchaType::class &&
                    $options['site_key'] === 'test_site_key' &&
                    $options['tagAction'] === 'test_tag_action';
            }));

        $event->expects($this->once())
            ->method('addValidator')
            ->with('plugin.recaptcha.validator', $this->isType('array'));

        $this->subscriber->onFormBuild($event);
    }

    public function testOnFormValidateSuccess(): void
    {
        $event = $this->createMock(ValidationEvent::class);

        $this->config->method('isPublished')->willReturn(true);
        $this->config->method('isConfigured')->willReturn(true);
        $this->recaptchaClient->method('verify')->willReturn(true);
        $event->expects($this->once())->method('getValue')->willReturn('');
        $event->expects($this->once())->method('getField')->willReturn(new Field());

        $event->expects($this->never())->method('failedValidation');

        $this->subscriber->onFormValidate($event);
    }

    public function testOnFormValidateFailure(): void
    {
        $event = $this->createMock(ValidationEvent::class);

        $this->config->method('isPublished')->willReturn(true);
        $this->config->method('isConfigured')->willReturn(true);
        $this->recaptchaClient->method('verify')->willReturn(false);
        $event->expects($this->once())->method('getValue')->willReturn('');
        $event->expects($this->once())->method('getField')->willReturn(new Field());

        $this->translator->method('trans')
            ->with('mautic.integration.recaptcha.failure_message')
            ->willReturn('reCAPTCHA was not successful.');

        $event->expects($this->once())
            ->method('failedValidation')
            ->with('reCAPTCHA was not successful.');

        $this->subscriber->onFormValidate($event);
    }

    public function testLeadPostSaveListener(): void
    {
        $leadEvent = $this->createMock(LeadEvent::class);
        $leadEvent->method('isNew')->willReturn(true);

        $lead = $this->createMock('Mautic\LeadBundle\Entity\Lead');
        $leadEvent->method('getLead')->willReturn($lead);

        $this->leadModel->expects($this->never())
            ->method('deleteEntity')
            ->with($lead);

        $this->eventDispatcher->method('addListener')
            ->with(LeadEvents::LEAD_POST_SAVE, $this->isType('callable'), -255)
            ->will($this->returnCallback(function ($eventName, $callback) use ($leadEvent) {
                $callback($leadEvent);
            }));

        $event = $this->createMock(ValidationEvent::class);
        $event->expects($this->once())->method('getValue')->willReturn('');
        $event->expects($this->once())->method('getField')->willReturn(new Field());

        $this->config->method('isPublished')->willReturn(true);
        $this->config->method('isConfigured')->willReturn(true);
        $this->recaptchaClient->method('verify')->willReturn(true);

        $this->subscriber->onFormValidate($event);
    }
}
