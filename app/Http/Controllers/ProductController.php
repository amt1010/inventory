<?php
 
namespace App\Http\Controllers;
use Illuminate\Support\Facades\DB; 
use App\Models\Product;
use App\Models\Adminquery;
use Illuminate\Http\Request;
 
class ProductController extends Controller
{
    public function addmenu(Request $req)
    {
    $user= new Product; 
    $user->name= $req->menu;
    $user->catgry_code= $req->catgry;
    $user->status= "Enable";

      echo $user->save();
      return back()->with('success', 'Category Added Successfully...!');  
    }
 
 
    public function destroy(Product $product)
    {
        $product->delete();
      
        return back()->with('success','Category deleted successfully');
    }

    public function uupdate($id)
    {
    	$category = Product::find($id);

	    return response()->json([
	      'data' => $category
	    ]);
    }

    public function eedit(Request $request)
    {
      $id= $request->color_id;
    
      DB::table('menu') 
      ->where('id',$id)
      ->update([ 'name' => $request->name, 
                 'catgry_code'=> $request->catgry_code,
                 'status'=> $request->status, ]);

return back()->with('success', 'Category Updated Successfully...!');

    }

   public function admnnquery(Request $req)
    {
    $user= new Adminquery; 
    $user->name= $req->menu;
    $user->query_for= $req->query_for;
    $user->code= $req->code;
    $user->user_id= $req->user_id;
    $user->reason= $req->reason;
    $user->status= "Pending..";

      echo $user->save();
      return back()->with('success', 'Query Send Successfully...!');  
    }
 

}