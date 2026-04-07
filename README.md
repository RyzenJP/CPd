# Inventory System v3

PHP/MySQL inventory system for XAMPP.

## Project Structure

- `index.php` - login / landing page
- `admin/` - admin pages
- `staff/` - staff pages
- `superadmin/` - superadmin pages
- `plugins/conn.php` - database connection
- `inventory.sql` - database dump
- `uploads/` - uploaded files (not committed, except placeholder files)

## Local Setup

1. Copy this project into `C:\xampp\htdocs\inventory3v3`
2. Start Apache and MySQL in XAMPP
3. Create a database named `inventory`
4. Import `inventory.sql` into that database
5. Open `http://localhost/inventory3v3`

## Notes

- The app currently uses `plugins/conn.php` for database settings.
- If this repo will be pushed to GitHub, review `plugins/conn.php` before publishing.
- The `uploads/` directory is ignored so user files do not get committed.

## Suggested Git Commands

```powershell
cd C:\xampp\htdocs\inventory3v3
git init
git add .
git commit -m "Initial commit"
```

## Suggested GitHub Flow

1. Create an empty repository on GitHub
2. Copy the repository URL
3. Run:

```powershell
git remote add origin <your-repo-url>
git branch -M main
git push -u origin main
```
