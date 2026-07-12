<?php
namespace App\Http\Controllers\Auth\Login;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Employee;

class EmployeeController extends Controller
{
    
    public function showLoginForm()
    {
        return view('auth.offcr_logn');
    }
    
    protected function guard()
    {
        return Auth::guard('employee');
    }

    public function login(Request $request){
        // validate data 
  //dd($request->all());

  $request->validate([
    'email' => 'required',
    'password' => 'required'
]);

// login code 

if(\Auth::attempt($request->only('email','password'))){
    $user = Auth::Employee();
    return redirect('home');
}

return redirect('offcr_logn')->withError('Login details are not valid');

    }
}