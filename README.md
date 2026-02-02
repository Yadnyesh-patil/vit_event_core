# VIT Event Core

**Module Name:** vit_event_core
**Description:** A custom Drupal 10 module for managing event registrations.

## Database Structure
This module installs two custom tables:

1.  **event_config**:
    *   `id`: Configuration ID
    *   `event_name`: Name of the event
    *   `category`: Type of event (e.g., Workshop)
    *   `event_date`: When the event happens
    *   `start_date` / `end_date`: Registration window

2.  **event_registrations**:
    *   `id`: Registration ID
    *   `full_name`, `email`, `college`, `department`: User details
    *   `category`, `event_name`: Snapshot of event details
    *   `created_at`: Timestamp

## Installation
1.  Enable the module: `drush en vit_event_core`
2.  Configure admin email at: `/admin/config/vit-event/settings` (Route pending Phase 2)
