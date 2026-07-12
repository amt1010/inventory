<?php

namespace App\Http\Controllers;
use App\Models\Submenu;
use App\Models\Thirdmenu;
use App\Models\Addproduct;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class SubmenuController extends Controller
{
    
    public function addsmenu(Request $req)
    {
    $user= new Submenu; 
    $user->name= $req->smenu;
    $user->m_name= $req->menu;
    $user->code= $req->scatcode;
    $user->status= "Enable";

      echo $user->save();
      return back()->with('success', 'SubCategory Added Successfully...!');  
    }
 
 
    public function delete(Request $request)
    {
      $cityId = $request->sc_id;
      
      // TODO: Check for validation
      
      DB::table('submenu')->where('id', $cityId)-> delete();
      
      
        return back()->with('success','SubCategory deleted successfully');
    }

    public function update($id)
    {
    	$category = Submenu::find($id);

	    return response()->json([
	      'data' => $category
	    ]);
    }

        public function updatev($id)
    {
      $category = Thirdmenu::find($id);

      return response()->json([
        'data' => $category
      ]);
    }

    public function edit(Request $request)
    {
      $id= $request->color_id;
    
      DB::table('submenu') 
      ->where('id',$id)
      ->update([ 'name' => $request->name, 
                 'code'=> $request->catgry_code,
                 'status'=> $request->status, ]);

return back()->with('success', 'Subcategory Updated Successfully...!');

    }

    public function thrdsmenu(Request $req)
    {
    $user= new Thirdmenu; 
    $user->name= $req->thcategory;
    $user->m_id= $req->menu;
    $user->sm_id= $req->psubcategry;
    $user->status= "Enable";

      echo $user->save();
      return back()->with('success', 'Third Category Added Successfully...!');  
    }

    public function editv(Request $request)
    {
      $id= $request->color_id;
    
      DB::table('thirdmenu') 
      ->where('id',$id)
      ->update([ 'name' => $request->name, 
                 'status'=> $request->status, ]);

return back()->with('success', 'Third Category Updated Successfully...!');

    }

      public function deletev(Request $request)
    {
      $cityId = $request->sc_id;
      
      // TODO: Check for validation
      
      DB::table('thirdmenu')->where('id', $cityId)-> delete();
      
      
        return back()->with('success','SubCategory deleted successfully');
    }



}
