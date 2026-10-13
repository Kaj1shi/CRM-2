# Factory CRM (PHP)

Local Factory CRM for XAMPP. The database starts empty except one administrator.

## Sign in

- Email: `piimasmith@gmail.com`
- Password: `smith001`

## Host it in XAMPP

The app is already installed in the XAMPP MariaDB database `factory_crm`.

Apache must serve this folder. From a terminal:

```bash
sudo ln -s "/home/theesmithsonian/Desktop/Projects/CRM V2" "/opt/lampp/htdocs/crm"
```

Open http://localhost/crm/

If the database was not created on this machine, open http://localhost/crm/install.php once and press **Create database**.

`config.php` uses the XAMPP default: host `127.0.0.1`, user `root`, empty password, database `factory_crm`.

## What is included

Clients, suppliers, products, purchases and supplies, dashboard, reports (CSV, Excel, PDF), users, audit log, and settings. Quantities in tonnes are stored as kilograms. Reversed transactions stay in history and leave the totals.
