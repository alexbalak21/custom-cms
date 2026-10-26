# Custom CMS

```bash
cp .env.example .env        # then edit DB_* values (create the MySQL database first)
php cms migrate             # users, sessions, login attempts, products tables
php cms make:user           # create your admin
php cms db:seed             # optional sample product
php cms serve               # http://localhost:8000  (admin: /admin)
```

Production: set the web server's document root to `public/`. Keep `.env`, `storage/`, `app/` outside the web root (they are, by design).

Layout: `routes/` (web + admin routes) · `app/` (core classes) · `views/` · `database/migrations/` · `storage/data/` (pages/blocks JSON) · `public/` (front controller, assets, uploads) · `cms` (console).
