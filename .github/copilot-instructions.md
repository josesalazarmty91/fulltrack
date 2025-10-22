# Copilot Instructions for fulltrack

## Project Overview
This is a PHP-based backend project with a static HTML frontend. The main logic resides in the `api/` directory, which contains multiple PHP scripts for handling different resources and operations. Uploaded files are stored in `api/uploads/`.

## Architecture & Data Flow
- **Frontend**: `index.html` is the main entry point. It likely interacts with the backend via AJAX or form submissions.
- **Backend**: The `api/` folder contains PHP scripts:
  - `assignments.php`, `operators.php`, `registrations.php`, `units.php`, `users.php`: Each script handles CRUD operations for a specific resource.
  - `upload.php`: Handles file uploads, storing them in `api/uploads/`.
  - `config.php`: Contains configuration, such as database connection details.
- **Uploads**: All uploaded files are stored in `api/uploads/` with unique filenames.

## Developer Workflows
- **No build step**: Static HTML and PHP files are served directly. No compilation required.
- **Testing**: No test framework detected. Manual testing via browser or API client (e.g., Postman) is typical.
- **Debugging**: Use browser dev tools for frontend, and PHP error logging for backend. Check `config.php` for error reporting settings.

## Project-Specific Conventions
- **File Naming**: Uploaded files use the pattern `photo_<unique_id>.jpg`.
- **API Endpoints**: Each PHP file in `api/` acts as a REST-like endpoint. Example: `api/users.php` for user operations.
- **Configuration**: Centralized in `api/config.php`.
- **No frameworks**: Pure PHP, no external libraries detected.

## Integration Points
- **Frontend/Backend Communication**: Likely via AJAX calls to `api/*.php` endpoints.
- **Uploads**: Frontend sends files to `api/upload.php`, which saves them in `api/uploads/`.

## Key Files & Directories
- `index.html`: Main frontend file.
- `api/`: Contains all backend logic.
- `api/config.php`: Configuration and database connection.
- `api/uploads/`: Stores uploaded files.

## Example Patterns
- To add a new resource, create a new PHP file in `api/` following the CRUD pattern of existing files.
- To change database settings, update `api/config.php`.
- To debug uploads, inspect `api/upload.php` and files in `api/uploads/`.

---

If any section is unclear or missing, please specify what needs more detail or examples.