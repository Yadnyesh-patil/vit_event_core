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
class EventSettingsForm extends ConfigFormBase {

  protected $database;
  protected $messenger;

  public function __construct(ConfigFactoryInterface $config_factory, Connection $database, MessengerInterface $messenger) {
    parent::__construct($config_factory);
    $this->database = $database;
    $this->messenger = $messenger;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('database'),
      $container->get('messenger')
    );
  }

  protected function getEditableConfigNames() {
    return ['vit_event_core.settings'];
  }

  public function getFormId() {
    return 'vit_event_core_settings';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('vit_event_core.settings');

    $form['general_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('General Configuration'),
      '#open' => TRUE,
    ];

    $form['general_settings']['enable_notifications'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Admin Notifications'),
      '#default_value' => $config->get('enable_notifications'),
    ];

    $form['general_settings']['admin_notification_email'] = [
      '#type' => 'email',
      '#title' => $this->t('Admin Notification Email'),
      '#default_value' => $config->get('admin_notification_email'),
      '#states' => [
        'visible' => [
          ':input[name="enable_notifications"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['add_event'] = [
      '#type' => 'details',
      '#title' => $this->t('Create New Event'),
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
        'Online Workshop' => $this->t('Online Workshop'),
        'Hackathon' => $this->t('Hackathon'),
        'Conference' => $this->t('Conference'),
        'One-day Workshop' => $this->t('One-day Workshop'),
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

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('vit_event_core.settings')
      ->set('enable_notifications', $form_state->getValue('enable_notifications'))
      ->set('admin_notification_email', $form_state->getValue('admin_notification_email'))
      ->save();

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
      }
      catch (\Exception $e) {
        $this->messenger->addError($this->t('Error creating event: @error', ['@error' => $e->getMessage()]));
      }
    }

    parent::submitForm($form, $form_state);
  }
}
