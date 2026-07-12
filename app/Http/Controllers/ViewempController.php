<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class ViewempController extends Controller
{
    public function Viewempdtl($id){
        return view('Employe.Employeedtl')->with(['id'=> $id]);
    }

    public function empdestroy(Request $request)
    {
        $cityId = $request->get('cityId');
      
        // TODO: Check for validation
        
        DB::table('users')->where('id', $cityId)-> delete();
        
        return back()->with('success','Employee deleted successfully');
    }

}
