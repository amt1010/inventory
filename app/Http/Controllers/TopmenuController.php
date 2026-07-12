<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Topmenu;
use Illuminate\Support\Facades\DB;
class TopmenuController extends Controller
{
    public function addtmenu(Request $req)
    {
    $user= new Topmenu; 
    $user->name= $req->menu;

      echo $user->save();
      return back()->with('success', 'Top Menu Added Successfully...!');  
    }
 
 
    public function tmdelete(Request $request)
    {
      $cityId = $request->get('cityId');
      
      // TODO: Check for validation
      
      DB::table('frst_menu')->where('id', $cityId)-> delete();
      
      
        return back()->with('success','Top Menu deleted successfully');
    }

    public function tuupdate($id)
    {
    	$category = Topmenu::find($id);

	    return response()->json([
	      'data' => $category
	    ]);
    }

    public function teedit(Request $request, $id)
    {
      Topmenu::updateOrCreate(
       [
        'id' => $id
       ],
       [
        'name' => $request->name,
       ]
      );

      return response()->json([ 'success' => true ]);

    }
}
