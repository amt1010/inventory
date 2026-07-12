<?php

namespace App\Http\Controllers;
use App\Models\Employee;
use Illuminate\Http\Request;
use Session;
class EmploginController extends Controller
{
    public function login(Request $request){
        // validate data 
  //dd($request->all());

  $request->validate([
    'email' => 'required',
    'password' => 'required'
]);

// login code 


    if (\Auth::guard('employees')->attempt(['email' => $request->email, 'password' => $request->password], $request->get('remember'))){ 
        $id = session()->get('id');
       // alert($id);
        return redirect('emphome')->with([ 'id' => $id ]);
}


return redirect('emplogin')->withError('Login details are not valid');

    }


    public function home(){
        return view('Employe.home');
    }

    public function emplogout(){
        \Session::flush();
        \Auth::logout();
        return redirect('user');
    }

}
