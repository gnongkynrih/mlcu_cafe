<?php

namespace App\Http\Controllers;

use App\Actions\Fortify\CreateNewUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

/**
 * Lets a LOGGED-IN user create accounts for other staff.
 *
 * Fortify's public "Sign up" is switched off in config/fortify.php, so
 * strangers cannot register. These routes sit inside the 'auth' middleware
 * group in routes/web.php. (Later, RBAC will limit this to admins only.)
 */
class RegisteredUserController extends Controller
{
    /**
     * Show the "Register User" form.
     */
    public function create(): View
    {
        //select id,name from roles
        $roles = Role::all(); //getting all the roles
        return view('pages::auth.register', compact('roles')); //passing the roles to the view
    }

    /**
     * Create the new user.
     *
     * We reuse Fortify's CreateNewUser action, which validates the input
     * (name, unique email, password + confirmation) and saves the user.
     * Unlike Fortify's sign up, we do NOT log in as the new user —
     * the person creating the account stays logged in.
     */
    public function store(Request $request, CreateNewUser $createNewUser): RedirectResponse
    {
        $user = $createNewUser->create($request->all());
       
        //assign the role
        $user->assignRole($request->role);
        
        return redirect()
            ->route('register')
            ->with('status', "Account for {$user->name} ({$user->email}) was created.");
    }
}
