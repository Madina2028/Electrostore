# Electrostore
E-commerce site for electronic devices

## Local database setup

Copy `config/db.local.example.php` to `config/db.local.php` and set your local
database credentials in the copied file. The local credentials file is excluded
from Git; do not commit real passwords or production credentials.

The application database account needs `SELECT`, `INSERT`, and `DELETE` access
to `login_attempts`, but does not need `CREATE` privileges. For an existing
database, run `database/migrations/2026-11-20-create-login-attempts.sql` once
with a database administrator against the configured database. Do not re-import
the full `Database.sql` into an existing database because it drops and recreates
the application tables.
