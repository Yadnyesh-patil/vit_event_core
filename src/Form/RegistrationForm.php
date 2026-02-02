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

/**
 * Provides the Event Registration Form with AJAX logic.
 */
class RegistrationForm extends FormBase
{

    /**
     * Database connection.
     *
     * @var \Drupal\Core\Database\Connection
     */
    protected $database;

    /**
     * Email validator.
     *
     * @var \Drupal\Component\Utility\EmailValidator
     */
    protected $emailValidator;

    /**
     * Messenger service.
     *
     * @var \Drupal\Core\Messenger\MessengerInterface
     */
    protected $messenger;

    /**
     * Request stack.
     *
     * @var \Symfony\Component\HttpFoundation\RequestStack
     */
    protected $requestStack;

    /**
     * Time service.
     *
     * @var \Drupal\Component\Datetime\TimeInterface
     */
    protected $time;

    /**
     * Constructor with strict Dependency Injection.
     */
    public function __construct(Connection $database, EmailValidator $email_validator, MessengerInterface $messenger, RequestStack $request_stack, TimeInterface $time)
    {
        $this->database = $database;
        $this->emailValidator = $email_validator;
        $this->messenger = $messenger;
        $this->requestStack = $request_stack;
        $this->time = $time;
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
            $container->get('datetime.time')
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

        // 1. Participant Details
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

        // 2. Event Selection (AJAX)
        $form['event_selection'] = [
            '#type' => 'details',
            '#title' => $this->t('Event Selection'),
            '#open' => TRUE,
        ];

        // Check availability of categories
        $categories = $this->fetchCategories();

        // AJAX: Category
        $selected_category = $form_state->getValue('category');

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

        // AJAX: Date Wrapper
        $form['event_selection']['date_wrapper'] = [
            '#type' => 'container',
            '#attributes' => ['id' => 'date-dropdown-wrapper'],
        ];

        $dates = [];
        if (!empty($selected_category)) {
            $dates = $this->fetchDates($selected_category);
        }

        $form['event_selection']['date_wrapper']['event_date'] = [
            '#type' => 'select',
            '#title' => $this->t('Select Date'),
            '#options' => $dates,
            '#empty_option' => $this->t('- Select Date -'),
            '#access' => !empty($dates) || !empty($selected_category), // Show if category selected
            '#ajax' => [
                'callback' => '::onDateChange',
                'wrapper' => 'event-dropdown-wrapper',
            ],
            '#validated' => TRUE,
        ];

        // AJAX: Event Name Wrapper
        $form['event_selection']['event_wrapper'] = [
            '#type' => 'container',
            '#attributes' => ['id' => 'event-dropdown-wrapper'],
        ];

        $events = [];
        $selected_date = $form_state->getValue('event_date');
        if (!empty($selected_category) && !empty($selected_date)) {
            $events = $this->fetchEvents($selected_category, $selected_date);
        }

        $form['event_selection']['event_wrapper']['event_name'] = [
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

    // --- AJAX Callbacks ---

    public function onCategoryChange(array &$form, FormStateInterface $form_state)
    {
        return $form['event_selection']['date_wrapper'];
    }

    public function onDateChange(array &$form, FormStateInterface $form_state)
    {
        return $form['event_selection']['event_wrapper'];
    }

    // --- Helper Methods (Private) ---

    private function fetchCategories()
    {
        try {
            $query = $this->database->select('event_config', 'ec');
            $query->fields('ec', ['category']);
            $query->distinct();
            $result = $query->execute()->fetchCol();
            return array_combine($result, $result);
        } catch (\Exception $e) {
            return [];
        }
    }

    private function fetchDates($category)
    {
        $query = $this->database->select('event_config', 'ec');
        $query->fields('ec', ['event_date']);
        $query->condition('category', $category);
        $query->distinct();
        return array_combine($result = $query->execute()->fetchCol(), $result);
    }

    private function fetchEvents($category, $date)
    {
        $query = $this->database->select('event_config', 'ec');
        $query->fields('ec', ['event_name', 'event_name']); // Key => Value
        $query->condition('category', $category);
        $query->condition('event_date', $date);
        return $query->execute()->fetchAllKeyed(0, 0); // Using name as key and value
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state)
    {
        // 1. Validate Email
        if (!$this->emailValidator->isValid($form_state->getValue('email'))) {
            $form_state->setErrorByName('email', $this->t('Invalid email format.'));
        }

        // 2. Check Duplicates (Email + Event Name)
        $email = $form_state->getValue('email');
        $event_name = $form_state->getValue('event_name');

        if ($email && $event_name) {
            $query = $this->database->select('event_registrations', 'er');
            $query->condition('email', $email);
            $query->condition('event_name', $event_name);
            $count = $query->countQuery()->execute()->fetchField();

            if ($count > 0) {
                $form_state->setErrorByName('email', $this->t('You have already registered for @event.', ['@event' => $event_name]));
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state)
    {
        try {
            $this->database->insert('event_registrations')
                ->fields([
                    'full_name' => $form_state->getValue('full_name'),
                    'email' => $form_state->getValue('email'),
                    'college' => $form_state->getValue('college'),
                    'department' => $form_state->getValue('department'),
                    'category' => $form_state->getValue('category'),
                    'event_name' => $form_state->getValue('event_name'),
                    'created_at' => $this->time->getRequestTime(),
                ])
                ->execute();

            $this->messenger->addStatus($this->t('Registration Successful!'));

            // Email triggering will be handled in Phase 4 via hook_mail
        } catch (\Exception $e) {
            $this->messenger->addError($this->t('Registration Failed.'));
        }
    }
}
