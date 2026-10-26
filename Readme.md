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

#ELOQUENT Relationships

we define the relationships in the model class
eg belongsTo(), hasMany()
  eg in the Category model 
 public function menuItems(){
  return $this->hasMany(MenuItem::class);
}
eg in the MenuItem model
public function category(){
  return $this->belongsTo(Category::class);
}

//WHEN WE UPLOAD IMAGES/FILES FROM LARAVEL WE WILL NEED TO GIVE PERMISSOIN OR CREATE A SYMBOLIC LINK TO THE STORAGE/PUBLIC FOLDER
//TO DO THAT FROM YOUR PROJECT FOLDER TYPE
php artisan storage:link

FOR ROLES AND PERMISSION WE WILL USE SPATIE/LARAVEL PERMISSION PACKAGE
https://spatie.be/docs/laravel-permission/v8/introduction
INSTALLATION
composer require spatie/laravel-permission
(if you pull this code then run composer isntall after downloading)
publish the migrations required
   --> php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
now migrate the tables
php artisan migrate

in the User model we will have to include
use Spatie\Permission\Traits\HasRoles;
and 
use HasRoles //inside the class

Now clear the cache
 php artisan optimize:clear

 CREATE THE ROLES AND PERMISSION IN THE SEEDER
 php artisan make:seeder RoleAndPermissionSeeder

 use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

$role = Role::create(['name' => 'writer']);
$permission = Permission::create(['name' => 'edit articles']);

after we write the roles and permission we execute the seeder
php artisan db:seed --class=RoleAndPermissionSeeder

//WHEN CREATING USER WE SHOULD ASSIGN THEM A ROLE
//eg
//To give option to select role during user creation
in the App/Http/Controllers/RegisteredUserController file we will load the roles and pass it to the view
$roles = Role::all();
$user = User::create([
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'password' => bcrypt('password'),
]);
$user->assignRole('admin');

to edit the registration page
resources/views/pages/auth/register.blade.php
  <flux:select
      name="role"
      :label="__('Role')"
      
      :placeholder="__('Select a role')"
      icon="user"
  >
      @foreach($roles as $role)
          <flux:select.option value="{{ $role->name }}">{{ $role->name }}</flux:select.option>
      @endforeach
  </flux:select>
  
Spatie package comes with RoleMiddleware, PermissionMiddleware and RoleOrPermissionMiddleware middleware.
You can register their aliases for easy reference elsewhere in your app:
Open /bootstrap/app.php and register them there:
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    // ...
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);
    })
    // ...

    BLADE and ROLES
    @role('writer') //if the user has role writer it will show i am a writer
    I am a writer!
@else
    I am not a writer...
@endrole
## Take Order page

See [ORDER-PAGE.md](ORDER-PAGE.md) for a step-by-step explanation of the cart, Send to Kitchen, and checkout.


PAYMENT GATEWAY
install razorpay
composer require razorpay/razorpay

//register with razor pay and generate test API keys
//add the keys to the .env file
RAZORPAY_KEY=your_key
RAZORPAY_SECRET=your_secret

For Razorpay, Take payment should:

Create a Razorpay order on the server for the bill total, in paise.
Open Razorpay Checkout in the browser.
Mark the cafe order paid and free the table only after the server verifies Razorpay's signature.

Razor treates amount is paise so 100 = 1 rupee

Popular Test Cards (Indian Payments)Use any future expiry date and any random CVV (e.g., 123 or 999).Card Network Card Number

4111 1111 1111 1111 
Domestic Mastercard 5267 3181 8797 5449 
Domestic Mastercard 5555 5555 5555 4444

After entering card details:Razorpay shows a mock bank page. Enter any OTP (4–10 digits) → Success. Enter short OTP (less than 4 digits) → Failure.

for internetbanking use 
test