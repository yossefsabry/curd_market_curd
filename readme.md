CRUD PHP + MySQL app with a polished UI.

Local run
- Copy `.env.example` to `.env` and set DB values (or edit `config.php`).
- Start PHP server: `php -S localhost:8000`

Railway deploy
- Run: `bash deploy_railway.sh`
- The script installs the Railway CLI (if needed), logs in, creates/links a project, adds MySQL, deploys, and prints your URL.
