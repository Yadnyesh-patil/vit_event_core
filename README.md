# VIT Event Core Module

**Description:**
A custom Drupal 10 module developed for the FOSSEE Screening Task. It handles event lifecycle management, user registration with AJAX dependencies, and administrative reporting.

## Installation Steps
1.  Copy the `vit_event_core` folder to your Drupal installation's `modules/custom` directory.
2.  Enable the module:
    *   Via Drush: `drush en vit_event_core`
    *   Via UI: Go to `/admin/modules` -> Search "VIT Event Core" -> Install.
3.  The database tables `event_config` and `event_registrations` will be created automatically.
4.  **Permissions:** Grant the "Administer VIT Event Settings" permission to the administrator role to access the configuration and report pages.

## URLs
*   **Event Configuration (Admin):** `/admin/config/vit-event/settings`
    *   Use this to add new events and configure notification settings.
*   **User Registration (Public):** `/event-registration`
    *   Includes AJAX-dependent dropdowns (Category -> Date -> Event).
*   **Admin Listing & Export:** `/admin/reports/vit-event/list`
    *   View registrations, filter by Date/Event, and Export to CSV.

## Database Schema
This module uses two custom tables:
1.  **`event_config`**: Stores the definitions of events created by the admin.
    *   Columns: `id`, `event_name`, `category`, `event_date`, `start_date`, `end_date`.
2.  **`event_registrations`**: Stores user submissions.
    *   Columns: `id`, `full_name`, `email`, `college`, `department`, `category`, `event_id`, `created_at`.
    *   **Foreign Key:** `event_id` references `event_config.id` (ON DELETE CASCADE).
    *   **Index:** `email` + `event_id` (Prevent duplicates).

## Logic Explanation

### Validation Logic
*   **Duplicate Check:** The module queries the `event_registrations` table. If a record with the same `email` and `event_id` exists, the form raises a validation error.
*   **Regex Check:** Participant names, college, and department fields validate used `preg_match` to allow only alphanumeric characters, spaces, dots, and hyphens.
*   **Date Window:** Events are only available for registration if the current date is between the `start_date` and `end_date`.

### Email Logic
*   The module implements `hook_mail` (`vit_event_core_mail`).
*   Upon successful registration, the `MailManager` service is used to send two emails:
    1.  **Receipt:** Sent to the participant.
    2.  **Notification:** Sent to the admin (if enabled in settings).
*   Emails contain all submission details.
