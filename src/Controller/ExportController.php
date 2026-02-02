<?php

namespace Drupal\vit_event_core\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Database\Connection;

/**
 * Handles CSV export of registrations.
 */
class ExportController extends ControllerBase
{

    /**
     * Database connection.
     *
     * @var \Drupal\Core\Database\Connection
     */
    protected $database;

    /**
     * Constructor.
     */
    public function __construct(Connection $database)
    {
        $this->database = $database;
    }

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container)
    {
        return new static(
            $container->get('database')
        );
    }

    /**
     * Generate CSV.
     */
    public function content()
    {
        $response = new StreamedResponse(function () {
            $handle = fopen('php://output', 'w+');

            // 1. Header Row
            fputcsv($handle, ['ID', 'Name', 'Email', 'College', 'Dept', 'Event', 'Category', 'Date']);

            // 2. Fetch Data
            $query = $this->database->select('event_registrations', 'er');
            $query->fields('er');
            $results = $query->execute();

            // 3. Write Data
            while ($row = $results->fetchAssoc()) {
                fputcsv($handle, [
                    $row['id'],
                    $row['full_name'],
                    $row['email'],
                    $row['college'],
                    $row['department'],
                    $row['event_name'],
                    $row['category'],
                    date('Y-m-d H:i:s', $row['created_at']),
                ]);
            }

            fclose($handle);
        });

        // Headers
        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="registrations.csv"');

        return $response;
    }
}
