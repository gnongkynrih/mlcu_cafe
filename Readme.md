after cloning the project you will need to run the command

npm install
composer install 
after you've clone the project to download the latest code
git pull origin main


1. To run the server
  1. composer run dev. (from your project folder -- terminal)
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

To create new livewirepage

```bash
php artisan make:livewire pages::pageName
```

The first letter of the file must not be capital letter

TO CREATE TABLE

1. CREATE THE MIGRATION FILE
  1. php artisan make:model ModelName -m (this will create two files the model and the migration file)
  2. eg... php artisan make:model Category -m
  3. (ModelName must start with Capital letter)
  4. Model Name is singular. the Table will be the plural name of the model and in small letter
  5. -m means create the migration file along with the model
2. TO ADD OR REMOVE COLUMN FROM AN EXISTING TABLE
  1. Create a new migration file
    1. eg. php artisan make:migration migration_name --table=table_names
    2. eg php artisan make:migration add_column_image_to_table_categories --table=categories

Factories and Seeders in Laravel are complementary tools for populating your database with data (especially during development and testing). They are not the same thing.

TO CREATE A FACTORY FILE

php artisan make:factory CategoryFactory

TO CREATE A SEEDER

php artisan make:seeder CategorySeeder

TO RUN THE SEEDER

1. TO EXECUTE SINGLE SEEDER
  1. php artisan db:seed --class= CategorySeeder

//wORKING ON CATEGORY CRUD

1. we first create the livewire page/component
  1. php artisan make:livewire Page::admin.categoryManagement


//TO USE TOAST
To use the Toast component from Livewire, you must include it somewhere on the page; often in your layout file inside the body:
eg
<body>
....
 <flux:toast />
</body>