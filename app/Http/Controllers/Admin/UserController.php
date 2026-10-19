<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    private function requireAdministrator(): void { abort_unless(auth()->user()?->isAdministrator(),403,'Only administrators can manage CMS users.'); }
    public function index(){ $this->requireAdministrator(); $users=User::query()->orderBy('name')->paginate(25); return view('admin.users.index',compact('users')); }
    public function create(){ $this->requireAdministrator(); return view('admin.users.form',['user'=>new User(),'isNew'=>true]); }
    public function store(Request $request){
        $this->requireAdministrator();
        $data=$request->validate(['name'=>['required','string','max:120'],'email'=>['required','email','max:190','unique:users,email'],'role'=>['required',Rule::in(['administrator','editor'])],'password'=>['required','string','min:14','confirmed']]);
        $data['is_active']=$request->boolean('is_active'); User::create($data); return redirect()->route('admin.users.index')->with('success','CMS user created.');
    }
    public function edit(User $user){$this->requireAdministrator();return view('admin.users.form',['user'=>$user,'isNew'=>false]);}
    public function update(Request $request,User $user){
        $this->requireAdministrator();
        $data=$request->validate(['name'=>['required','string','max:120'],'email'=>['required','email','max:190',Rule::unique('users','email')->ignore($user->id)],'role'=>['required',Rule::in(['administrator','editor'])],'password'=>['nullable','string','min:14','confirmed']]);
        $data['is_active']=$request->boolean('is_active');
        if ($user->id===auth()->id() && (!$data['is_active'] || $data['role']!=='administrator')) return back()->withErrors(['role'=>'You cannot disable your own account or remove your own administrator role.'])->withInput();
        if ($user->role==='administrator' && (!$data['is_active'] || $data['role']!=='administrator') && User::where('role','administrator')->where('is_active',true)->count()<=1) return back()->withErrors(['role'=>'At least one active administrator must remain.'])->withInput();
        if (empty($data['password'])) unset($data['password']);
        $user->update($data); return back()->with('success','CMS user updated.');
    }
}
