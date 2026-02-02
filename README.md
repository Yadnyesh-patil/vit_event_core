# VIT Event Core Module

**Description:**
A custom Drupal 10 module developed for the FOSSEE Screening Task. It handles event lifecycle management, user registration with AJAX dependencies, and administrative reporting.

## Installation Steps
1.  Copy the `vit_event_core` folder to your Drupal installation's `modules/custom` directory.
2.  Enable the module:
    *   Via Drush: `drush en vit_event_core`
    *   Via UI: Go to `/admin/modules` -> Search "VIT Event Core" -> Install.
3.  The database tables `event_config` and `event_registrations` will be created automatically upon installation.

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
    *   Columns: `id`, `full_name`, `email`, `college`, `department`, `category`, `event_name`, `created_at`.
    *   Includes an index on `email` + `event_name` for duplicate checks.

## Logic Explanation

### Validation Logic
*   **Duplicate Check:** The module queries the `event_registrations` table before saving. If the combination of `email` and `event_name` exists, the form raises an error.
*   **Regex Check:** Participant names, college, and department fields are validated using PHP `preg_match` to allow only alphanumeric characters, spaces, dots, and hyphens. Special characters trigger a validation error.
*   **Date Window:** Events are only available for registration if the current date falls between the `start_date` and `end_date` configured for that event.

### Email Logic
*   The module implements `hook_mail` (`vit_event_core_mail`).
*   Upon successful registration, `\Drupal::service('plugin.manager.mail')` is called twice:
    1.  **Receipt:** Sent to the user's email.
    2.  **Notification:** Sent to the admin email (if "Enable Notifications" is checked in settings).
*   Emails contain the Participant Name, Event Name, Date, and Category.
