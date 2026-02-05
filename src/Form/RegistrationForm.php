<?php

namespace Drupal\vit_event_core\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Database\Connection;
use Drupal\Component\Utility\EmailValidator;
use Drupal\Core\Messenger\MessengerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Mail\MailManagerInterface;

/**
 * Provides a registration form for events.
 */
class RegistrationForm extends FormBase
{

    /**
     * The database connection.
     *
     * @var \Drupal\Core\Database\Connection
     */
    protected $database;

    /**
     * The email validator service.
     *
     * @var \Drupal\Component\Utility\EmailValidator
     */
    protected $emailValidator;

    /**
     * The messenger service.
     *
     * @var \Drupal\Core\Messenger\MessengerInterface
     */
    protected $messenger;

    /**
     * The request stack.
     *
     * @var \Symfony\Component\HttpFoundation\RequestStack
     */
    protected $requestStack;

    /**
     * The time service.
     *
     * @var \Drupal\Component\Datetime\TimeInterface
     */
    protected $time;

    /**
     * The config factory.
     *
     * @var \Drupal\Core\Config\ConfigFactoryInterface
     */
    protected $configFactory;

    /**
     * The mail manager.
     *
     * @var \Drupal\Core\Mail\MailManagerInterface
     */
    protected $mailManager;

    /**
     * Constructs a new RegistrationForm.
     *
     * @param \Drupal\Core\Database\Connection $database
     *   The database connection.
     * @param \Drupal\Component\Utility\EmailValidator $email_validator
     *   The email validator.
     * @param \Drupal\Core\Messenger\MessengerInterface $messenger
     *   The messenger service.
     * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
     *   The request stack.
     * @param \Drupal\Component\Datetime\TimeInterface $time
     *   The time service.
     * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
     *   The config factory.
     * @param \Drupal\Core\Mail\MailManagerInterface $mail_manager
     *   The mail manager.
     */
    public function __construct(Connection $database, EmailValidator $email_validator, MessengerInterface $messenger, RequestStack $request_stack, TimeInterface $time, ConfigFactoryInterface $config_factory, MailManagerInterface $mail_manager)
    {
        $this->database = $database;
        $this->emailValidator = $email_validator;
        $this->messenger = $messenger;
        $this->requestStack = $request_stack;
        $this->time = $time;
        $this->configFactory = $config_factory;
        $this->mailManager = $mail_manager;
    }

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container)
    {
        return new static(
            $container->get('database'),
            $container->get('email.validator'),
            $container->get('messenger'),
            $container->get('request_stack'),
            $container->get('datetime.time'),
            $container->get('config.factory'),
            $container->get('plugin.manager.mail')
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getFormId()
    {
        return 'vit_event_registration_form';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state)
    {
        $form['#prefix'] = '<div id="vit-registration-wrapper">';
        $form['#suffix'] = '</div>';

        $form['details'] = [
            '#type' => 'details',
            '#title' => $this->t('Participant Details'),
            '#open' => TRUE,
        ];

        $form['details']['full_name'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Full Name'),
            '#required' => TRUE,
        ];

        $form['details']['email'] = [
            '#type' => 'email',
            '#title' => $this->t('Email Address'),
            '#required' => TRUE,
        ];

        $form['details']['college'] = [
            '#type' => 'textfield',
            '#title' => $this->t('College / Institute'),
            '#required' => TRUE,
        ];

        $form['details']['department'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Department'),
            '#required' => TRUE,
        ];

        $form['event_selection'] = [
            '#type' => 'details',
            '#title' => $this->t('Event Selection'),
            '#open' => TRUE,
        ];

        $categories = $this->fetchCategories();

        $form['event_selection']['category'] = [
            '#type' => 'select',
            '#title' => $this->t('Select Category'),
            '#options' => $categories,
            '#empty_option' => $this->t('- Select Category -'),
            '#required' => TRUE,
            '#ajax' => [
                'callback' => '::onCategoryChange',
                'wrapper' => 'date-dropdown-wrapper',
            ],
        ];

        // WRAPPER 1: Dependent on Category
        $form['event_selection']['date_wrapper'] = [
            '#type' => 'container',
            '#attributes' => ['id' => 'date-dropdown-wrapper'],
        ];

        $dates = [];
        $selected_category = $form_state->getValue('category');
        if (!empty($selected_category)) {
            $dates = $this->fetchDates($selected_category);
        }

        $form['event_selection']['date_wrapper']['event_date'] = [
            '#type' => 'select',
            '#title' => $this->t('Select Date'),
            '#options' => $dates,
            '#empty_option' => $this->t('- Select Date -'),
            '#access' => !empty($dates) || !empty($selected_category),
            '#ajax' => [
                'callback' => '::onDateChange',
                'wrapper' => 'event-dropdown-wrapper',
            ],
            '#validated' => TRUE,
        ];

        // WRAPPER 2: Nested inside date_wrapper conceptually, or updated by date
        // Note: We place it INSIDE date_wrapper so that when date_wrapper updates (due to Category change),
        // this event_wrapper is also reset/removed.
        $form['event_selection']['date_wrapper']['event_wrapper'] = [
            '#type' => 'container',
            '#attributes' => ['id' => 'event-dropdown-wrapper'],
        ];

        $events = [];
        $selected_date = $form_state->getValue('event_date');

        // Only fetch events if both category AND date are selected.
        // When Category changes, 'event_date' is null/reset in the new form state, 
        // so this will be empty, effectively clearing the 3rd dropdown.
        if (!empty($selected_category) && !empty($selected_date)) {
            $events = $this->fetchEvents($selected_category, $selected_date);
        }

        $form['event_selection']['date_wrapper']['event_wrapper']['event_id'] = [
            '#type' => 'select',
            '#title' => $this->t('Select Event'),
            '#options' => $events,
            '#empty_option' => $this->t('- Select Event -'),
            '#access' => !empty($events) || (!empty($selected_category) && !empty($selected_date)),
            '#validated' => TRUE,
        ];

        $form['actions']['submit'] = [
            '#type' => 'submit',
            '#value' => $this->t('Register Now'),
        ];

        return $form;
    }

    /**
     * AJAX callback for category change.
     * Returns the entire date wrapper, which now INCLUDES the event wrapper.
     */
    public function onCategoryChange(array &$form, FormStateInterface $form_state)
    {
        return $form['event_selection']['date_wrapper'];
    }

    /**
     * AJAX callback for date change.
     * Returns only the event wrapper (nested inside).
     */
    public function onDateChange(array &$form, FormStateInterface $form_state)
    {
        return $form['event_selection']['date_wrapper']['event_wrapper'];
    }

    /**
     * Helper to fetch categories.
     */
    private function fetchCategories()
    {
        try {
            $today = date('Y-m-d');
            $query = $this->database->select('event_config', 'ec');
            $query->fields('ec', ['category']);
            $query->condition('start_date', $today, '<=');
            $query->condition('end_date', $today, '>=');
            $query->distinct();
            $result = $query->execute()->fetchCol();
            return $result ? array_combine($result, $result) : [];
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Helper to fetch dates for a category.
     */
    private function fetchDates($category)
    {
        $today = date('Y-m-d');
        $query = $this->database->select('event_config', 'ec');
        $query->fields('ec', ['event_date']);
        $query->condition('category', $category);
        $query->condition('start_date', $today, '<=');
        $query->condition('end_date', $today, '>=');
        $query->distinct();
        $result = $query->execute()->fetchCol();
        return $result ? array_combine($result, $result) : [];
    }

    /**
     * Helper to fetch events for a category and date.
     */
    private function fetchEvents($category, $date)
    {
        $today = date('Y-m-d');
        $query = $this->database->select('event_config', 'ec');
        $query->fields('ec', ['id', 'event_name']);
        $query->condition('category', $category);
        $query->condition('event_date', $date);
        $query->condition('start_date', $today, '<=');
        $query->condition('end_date', $today, '>=');
        return $query->execute()->fetchAllKeyed(0, 1);
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state)
    {
        if (!$this->emailValidator->isValid($form_state->getValue('email'))) {
            $form_state->setErrorByName('email', $this->t('Invalid email format.'));
        }

        $text_fields = ['full_name', 'college', 'department'];
        foreach ($text_fields as $field) {
            $value = $form_state->getValue($field);
            if (preg_match('/[^a-zA-Z0-9\s\.\-]/', $value)) {
                $form_state->setErrorByName($field, $this->t('Special characters are not allowed in @field.', ['@field' => str_replace('_', ' ', ucfirst($field))]));
            }
        }

        $email = $form_state->getValue('email');
        $event_id = $form_state->getValue('event_id');

        if ($email && $event_id) {
            $query = $this->database->select('event_registrations', 'er');
            $query->condition('email', $email);
            $query->condition('event_id', $event_id);
            $count = $query->countQuery()->execute()->fetchField();

            if ($count > 0) {
                $e_name = $this->getEventName($event_id);
                $form_state->setErrorByName('email', $this->t('You have already registered for @event.', ['@event' => $e_name]));
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state)
    {
        try {
            $event_id = $form_state->getValue('event_id');

            $this->database->insert('event_registrations')
                ->fields([
                    'full_name' => $form_state->getValue('full_name'),
                    'email' => $form_state->getValue('email'),
                    'college' => $form_state->getValue('college'),
                    'department' => $form_state->getValue('department'),
                    'category' => $form_state->getValue('category'),
                    'event_id' => $event_id,
                    'created_at' => $this->time->getRequestTime(),
                ])
                ->execute();

            $this->messenger->addStatus($this->t('Registration Successful!'));

            $event_name = $this->getEventName($event_id);

            // Use injected Config Factory.
            $config = $this->configFactory->get('vit_event_core.settings');

            $params = [
                'full_name' => $form_state->getValue('full_name'),
                'event_name' => $event_name,
                'email' => $form_state->getValue('email'),
                'event_date' => $form_state->getValue('event_date'),
                'category' => $form_state->getValue('category'),
            ];

            // Use injected Mail Manager.
            $this->mailManager->mail('vit_event_core', 'registration_receipt', $params['email'], 'en', $params);

            if ($config->get('enable_notifications')) {
                $admin_email = $config->get('admin_notification_email');
                if ($admin_email) {
                    $this->mailManager->mail('vit_event_core', 'admin_notification', $admin_email, 'en', $params);
                }
            }
        } catch (\Exception $e) {
            $this->messenger->addError($this->t('Registration Failed: @error', ['@error' => $e->getMessage()]));
        }
    }

    /**
     * Helper to get event name by ID.
     */
    private function getEventName($id)
    {
        if (!$id) {
            return '';
        }
        $query = $this->database->select('event_config', 'ec');
        $query->fields('ec', ['event_name']);
        $query->condition('id', $id);
        return $query->execute()->fetchField();
    }

}
