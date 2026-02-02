<?php

namespace Drupal\vit_event_core\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Database\Connection;

class ExportController extends ControllerBase
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

    public function content()
    {
        $response = new StreamedResponse(function () {
            $handle = fopen('php://output', 'w+');

            // Header Row
            fputcsv($handle, ['ID', 'Name', 'Email', 'College', 'Dept', 'Category', 'Event Name', 'Event Date', 'Registered At']);

            // Fetch Data with Join
            $query = $this->database->select('event_registrations', 'er');
            $query->join('event_config', 'ec', 'er.event_id = ec.id');
            $query->fields('er');
            $query->fields('ec', ['event_name', 'event_date']);
            $results = $query->execute();

            while ($row = $results->fetchAssoc()) {
                fputcsv($handle, [
                    $row['id'],
                    $row['full_name'],
                    $row['email'],
                    $row['college'],
                    $row['department'],
                    $row['category'],
                    $row['event_name'],
                    $row['event_date'],
                    date('Y-m-d H:i:s', $row['created_at']),
                ]);
            }

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="registrations.csv"');

        return $response;
    }
}
