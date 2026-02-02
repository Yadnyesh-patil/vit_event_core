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

class RegistrationForm extends FormBase
{

    protected $database;
    protected $emailValidator;
    protected $messenger;
    protected $requestStack;
    protected $time;

    public function __construct(Connection $database, EmailValidator $email_validator, MessengerInterface $messenger, RequestStack $request_stack, TimeInterface $time)
    {
        $this->database = $database;
        $this->emailValidator = $email_validator;
        $this->messenger = $messenger;
        $this->requestStack = $request_stack;
        $this->time = $time;
    }

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

    public function getFormId()
    {
        return 'vit_event_registration_form';
    }

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

        $form['event_selection']['event_wrapper'] = [
            '#type' => 'container',
            '#attributes' => ['id' => 'event-dropdown-wrapper'],
        ];

        $events = [];
        $selected_date = $form_state->getValue('event_date');
        if (!empty($selected_category) && !empty($selected_date)) {
            $events = $this->fetchEvents($selected_category, $selected_date);
        }

        $form['event_selection']['event_wrapper']['event_id'] = [
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

    public function onCategoryChange(array &$form, FormStateInterface $form_state)
    {
        return $form['event_selection']['date_wrapper'];
    }

    public function onDateChange(array &$form, FormStateInterface $form_state)
    {
        return $form['event_selection']['event_wrapper'];
    }

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

    private function fetchEvents($category, $date)
    {
        $today = date('Y-m-d');
        $query = $this->database->select('event_config', 'ec');
        $query->fields('ec', ['id', 'event_name']); // ID as Key
        $query->condition('category', $category);
        $query->condition('event_date', $date);
        $query->condition('start_date', $today, '<=');
        $query->condition('end_date', $today, '>=');
        return $query->execute()->fetchAllKeyed(0, 1); // ID => Name
    }

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
            $config = \Drupal::config('vit_event_core.settings');
            $params = [
                'full_name' => $form_state->getValue('full_name'),
                'event_name' => $event_name,
                'email' => $form_state->getValue('email'),
                'event_date' => $form_state->getValue('event_date'),
                'category' => $form_state->getValue('category'),
            ];

            \Drupal::service('plugin.manager.mail')->mail('vit_event_core', 'registration_receipt', $params['email'], 'en', $params);

            if ($config->get('enable_notifications')) {
                $admin_email = $config->get('admin_notification_email');
                if ($admin_email) {
                    \Drupal::service('plugin.manager.mail')->mail('vit_event_core', 'admin_notification', $admin_email, 'en', $params);
                }
            }
        } catch (\Exception $e) {
            $this->messenger->addError($this->t('Registration Failed: @error', ['@error' => $e->getMessage()]));
        }
    }

    private function getEventName($id)
    {
        $query = $this->database->select('event_config', 'ec');
        $query->fields('ec', ['event_name']);
        $query->condition('id', $id);
        return $query->execute()->fetchField();
    }
}
