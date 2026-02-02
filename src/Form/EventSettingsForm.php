<?php

namespace Drupal\vit_event_core\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Messenger\MessengerInterface;

/**
 * Configure VIT Event Core settings and manage events.
 */
class EventSettingsForm extends ConfigFormBase
{

    /**
     * The database connection.
     *
     * @var \Drupal\Core\Database\Connection
     */
    protected $database;

    /**
     * The messenger service.
     *
     * @var \Drupal\Core\Messenger\MessengerInterface
     */
    protected $messenger;

    /**
     * Constructs a new EventSettingsForm.
     *
     * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
     *   The factory for configuration objects.
     * @param \Drupal\Core\Database\Connection $database
     *   The database connection.
     * @param \Drupal\Core\Messenger\MessengerInterface $messenger
     *   The messenger service.
     */
    public function __construct(ConfigFactoryInterface $config_factory, Connection $database, MessengerInterface $messenger)
    {
        parent::__construct($config_factory);
        $this->database = $database;
        $this->messenger = $messenger;
    }

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container)
    {
        return new static(
            $container->get('config.factory'),
            $container->get('database'),
            $container->get('messenger')
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function getEditableConfigNames()
    {
        return ['vit_event_core.settings'];
    }

    /**
     * {@inheritdoc}
     */
    public function getFormId()
    {
        return 'vit_event_core_settings';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state)
    {
        $config = $this->config('vit_event_core.settings');

        // Section 1: General Module Configuration
        $form['general_settings'] = [
            '#type' => 'details',
            '#title' => $this->t('General Configuration'),
            '#open' => TRUE,
        ];

        $form['general_settings']['admin_notification_email'] = [
            '#type' => 'email',
            '#title' => $this->t('Admin Notification Email'),
            '#description' => $this->t('Notifications of new registrations will be sent to this address.'),
            '#default_value' => $config->get('admin_notification_email'),
            '#required' => TRUE,
        ];

        // Section 2: Add New Event
        // Note: In a real module, this might be a separate entity form, but per constraints we do it here.
        $form['add_event'] = [
            '#type' => 'details',
            '#title' => $this->t('Create New Event'),
            '#description' => $this->t('Use this section to add a new event to the system.'),
            '#open' => TRUE,
        ];

        $form['add_event']['event_name'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Event Name'),
        ];

        $form['add_event']['category'] = [
            '#type' => 'select',
            '#title' => $this->t('Category'),
            '#options' => [
                'Conference' => $this->t('Conference'),
                'Workshop' => $this->t('Workshop'),
                'Webinar' => $this->t('Webinar'),
                'Hackathon' => $this->t('Hackathon'),
            ],
            '#empty_option' => $this->t('- Select Category -'),
        ];

        $form['add_event']['event_date'] = [
            '#type' => 'date',
            '#title' => $this->t('Event Date'),
        ];

        $form['add_event']['start_date'] = [
            '#type' => 'date',
            '#title' => $this->t('Registration Start Date'),
        ];

        $form['add_event']['end_date'] = [
            '#type' => 'date',
            '#title' => $this->t('Registration End Date'),
        ];

        return parent::buildForm($form, $form_state);
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state)
    {
        // 1. Save Config
        $this->config('vit_event_core.settings')
            ->set('admin_notification_email', $form_state->getValue('admin_notification_email'))
            ->save();

        // 2. Handle Event Creation (if details provided)
        $eventName = $form_state->getValue('event_name');
        $eventDate = $form_state->getValue('event_date');

        if (!empty($eventName) && !empty($eventDate)) {
            try {
                $this->database->insert('event_config')
                    ->fields([
                        'event_name' => $eventName,
                        'category' => $form_state->getValue('category'),
                        'event_date' => $eventDate,
                        'start_date' => $form_state->getValue('start_date'),
                        'end_date' => $form_state->getValue('end_date'),
                    ])
                    ->execute();

                $this->messenger->addStatus($this->t('Event "@event" created successfully.', ['@event' => $eventName]));
            } catch (\Exception $e) {
                $this->messenger->addError($this->t('Error creating event: @error', ['@error' => $e->getMessage()]));
            }
        }

        parent::submitForm($form, $form_state);
    }

}
