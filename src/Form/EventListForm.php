<?php

namespace Drupal\vit_event_core\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Database\Connection;

class EventListForm extends FormBase
{

    protected $database;

    public function __construct(Connection $database)
    {
        $this->database = $database;
    }

    public static function create(ContainerInterface $container)
    {
        return new static(
            $container->get('database')
        );
    }

    public function getFormId()
    {
        return 'vit_event_list_form';
    }

    public function buildForm(array $form, FormStateInterface $form_state)
    {

        $form['filters'] = [
            '#type' => 'container',
            '#attributes' => ['class' => ['container-inline']],
        ];

        $query = $this->database->select('event_config', 'ec');
        $query->fields('ec', ['event_date']);
        $query->distinct();
        $dates = $query->execute()->fetchCol();
        $date_options = $dates ? array_combine($dates, $dates) : [];

        $selected_date = $form_state->getValue('filter_date');

        $form['filters']['filter_date'] = [
            '#type' => 'select',
            '#title' => $this->t('Filter by Date'),
            '#options' => $date_options,
            '#empty_option' => $this->t('- Any Date -'),
            '#ajax' => [
                'callback' => '::onFilterChange',
                'wrapper' => 'admin-list-wrapper',
            ],
        ];

        $name_options = [];
        if ($selected_date) {
            $query = $this->database->select('event_config', 'ec');
            $query->fields('ec', ['id', 'event_name']);
            $query->condition('event_date', $selected_date);
            $names = $query->execute()->fetchAllKeyed(0, 1); // ID => Name
            $name_options = $names;
        }

        $form['filters']['filter_event_id'] = [
            '#type' => 'select',
            '#title' => $this->t('Filter by Event Name'),
            '#options' => $name_options,
            '#empty_option' => $this->t('- Any Event -'),
            '#disabled' => empty($selected_date),
            '#ajax' => [
                'callback' => '::onFilterChange',
                'wrapper' => 'admin-list-wrapper',
            ],
        ];

        $form['actions']['export'] = [
            '#type' => 'link',
            '#title' => $this->t('Export All to CSV'),
            '#url' => \Drupal\Core\Url::fromRoute('vit_event_core.export'),
            '#attributes' => ['class' => ['button', 'button--primary']],
        ];

        $form['list_wrapper'] = [
            '#type' => 'container',
            '#attributes' => ['id' => 'admin-list-wrapper'],
        ];

        $header = [
            'Name',
            'Email',
            'Event Name',
            'Event Date',
            'College',
            'Department',
            'Submission Date'
        ];

        $rows = [];
        $selected_id = $form_state->getValue('filter_event_id');

        $query = $this->database->select('event_registrations', 'er');
        $query->join('event_config', 'ec', 'er.event_id = ec.id'); // JOIN
        $query->fields('er');
        $query->fields('ec', ['event_name', 'event_date']);

        // Filter
        if ($selected_id) {
            $query->condition('er.event_id', $selected_id);
        } elseif ($selected_date) {
            $query->condition('ec.event_date', $selected_date);
        }

        $results = $query->execute()->fetchAll();

        foreach ($results as $row) {
            $rows[] = [
                $row->full_name,
                $row->email,
                $row->event_name,
                $row->event_date,
                $row->college,
                $row->department,
                date('Y-m-d H:i', $row->created_at),
            ];
        }

        $form['list_wrapper']['count'] = [
            '#markup' => '<h3>' . $this->t('Total Participants: @count', ['@count' => count($rows)]) . '</h3>',
        ];

        $form['list_wrapper']['table'] = [
            '#type' => 'table',
            '#header' => $header,
            '#rows' => $rows,
            '#empty' => $this->t('No registrations found.'),
        ];

        return $form;
    }

    public function onFilterChange(array &$form, FormStateInterface $form_state)
    {
        return $form['list_wrapper'];
    }

    public function submitForm(array &$form, FormStateInterface $form_state)
    {
    }
}
