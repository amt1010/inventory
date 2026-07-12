<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Lastmenu;
use Illuminate\Support\Facades\DB;
class LastmenuController extends Controller
{
    public function addlmenu(Request $req)
    {
    $user= new Lastmenu; 
    $user->name= $req->menu;

      echo $user->save();
      return back()->with('success', 'Third Menu Added Successfully...!');  
    }
 
 
    public function lmdelete(Request $request)
    {
      $cityId = $request->get('cityId');
      
      // TODO: Check for validation
      
      DB::table('last_menu')->where('id', $cityId)-> delete();
      
      
        return back()->with('success','Third Menu deleted successfully');
    }

    public function luupdate($id)
    {
    	$category = Lastmenu::find($id);

	    return response()->json([
	      'data' => $category
	    ]);
    }

    public function leedit(Request $request, $id)
    {
      Lastmenu::updateOrCreate(
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
