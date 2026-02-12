<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $users = User::query()
            ->orderBy('created_at','desc')
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%");
                    $q->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->paginate(10)
            ->withQueryString();
        

        return Inertia::render('Admin/Users/Index', [
            'modules' => $users,
            'filters' => $request->only('search'),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return Inertia::render('Admin/Users/Create');   
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = (object) $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => 'nullable|string|max:20',
        ]);
            

        $mi = strlen($data->middle_name) > 0 ? strtoupper($data->middle_name[0]) . '. ' : '';

        $user = new User();
        $user->name = "$data->first_name $mi $data->last_name";
        $user->first_name = $data->first_name;
        $user->last_name = $data->last_name;
        $user->middle_name = $data->middle_name;
        $user->email = $data->email;
        $user->phone = $data->phone;
        $user->password = Hash::make('password'); // Default password, should be changed later
        $user->save();

        Inertia::flash([
            'header' => "Create success",
            'message' => "You have successfully created user $user->name"
        ]);

        return to_route('admin.user.edit', $user->id);
    }

    /**
     * Display the specified resource.
     */
    public function show(User $user)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(User $user)
    {
        return Inertia::render('Admin/Users/Edit', [
            'module' => $user,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, User $user)
    {
        $data = (object) $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'email' => "required|email|unique:users,email,{$user->id}",
            'phone' => 'nullable|string|max:20',
        ]);


        foreach($data as $col => $val)
            $user->$col = $val;


        $user->name = trim($data->first_name . ' ' . (strlen($data->middle_name) > 0 ? strtoupper($data->middle_name[0]) . '. ' : '') . $data->last_name); 
        $user->save();

        Inertia::flash([
            'header' => "Update success",
            'message' => "You have successfully updated user $user->name"
        ]);

        return to_route('admin.user.edit',$user->id);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user)
    {
        $user->delete();
        Inertia::flash([
            'header' => "Delete success",
            'message' => "You have successfully removed user $user->name"
        ]);

        return to_route('admin.user.index');
    }
}
