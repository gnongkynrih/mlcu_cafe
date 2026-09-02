1. To run the server
  1. composer run dev
2. configure the database
  1. In Laravel, the configuration is usually done in .env file
  2. ```dotenv
    DB_CONNECTION=mysql
    DB_HOST=127.0.0.1
    DB_PORT=3306
    DB_DATABASE=mlcu # your database name
    DB_USERNAME=root #your username
    DB_PASSWORD=root #your database password
    ```
  3. To migrate the tables
    1. php artisan migrate
  4. To migrate fresh (i.e existing data will be deleted)
    1. php artisan migrate:fresh

Creating tables

1. In Laravel we create tables using migration files (inside database folder)


