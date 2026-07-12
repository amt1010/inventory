<?php

namespace App\Http\Controllers;
use App\Models\User;
use App\Models\Addproduct;
use App\Models\Adminquery;
use App\Models\Product_image;
use Illuminate\Support\Facades\DB;


use Illuminate\Http\Request;

class AddemployeeController extends Controller
{
    
    public function addemp(Request $req)
    {
        
     //print_r($req->input());
    $req->validate([
        'image' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        'email' => 'required|email|unique:users',
      //  'pass' => 'min:4',
       // 'cpass' => 'required_with:password|same:pass|min:4'
       ]);

       
    $user= new User; 
    $user->name= $req->name;
    $user->email= $req->email;
    $user->contact_no= $req->phone;
    $user->designation= $req->design; 
    //$user->password= \Hash::make($req->pass);
    $user->password= \Hash::make("webhut");
    $user->factry_name= $req->factry_nm;
    $user->factry_detail= $req->factory_detl;
    $user->city= $req->city;
    $user->state= $req->state;
    $user->full_addrs= $req->addrss;
    $user->status= "Disable"; 
    
       $imageName = time().'.'.$req->image->extension();  
       $req->image->move(public_path('image/employee_image'), $imageName);
      $user->image= $imageName;
    
       echo $user->save();
      return back()->with('success', 'User Added Successfully...!');  
    }


    public function inputArrayToString(Request $req)
    {          
       $user= new Addproduct;
                
       $user->name= $req->pname;
       $user->category= $req->pcategry; 
       $user->subcategory= $req->psubcategry;
       $user->thrdcatgry= $req->pthrcategry;
       $user->company= $req->pcompny;
       $user->quantity= $req->pquantity; 
       $user->price= $req->pprice; 
       $user->descrp = $req->pdesc;
       $user->status= "Enable";

      $imageName = time().'.'.$req->image->extension();  
       $req->image->move(public_path('image/product_image'), $imageName);
      $user->image= $imageName;
    
        echo $user->save();
       $lastId = $user->id;
  //return back()->with('success', 'New Product Add Successfully...!');

            $age= $req->imageo;
           $files = [];
        if($req->hasfile('imageo'))
         {
            foreach($age as $file)
            {
                $name = time().rand(1,50).'.'.$file->extension();
                $file->move(public_path('image/product_image'), $name);  
                $files[] = $name;  
            }
         }
for($count = 0; $count < count($age); $count++)
        {
        $data = array(
           
                'p_id' => $lastId,
                'image' => $files[$count],
          
         );


         $insert_data[] = $data; 
        }
        Product_image::insert($insert_data);
         
return back()->with('success', 'New Product Add Successfully...!'); 

       }

    public function fetchdetail($id)
    {
        return view('Employe.View_product')->with(['id'=> $id]);
    }

    public function editproduct($id)
    {
        return view('Employe.Edit_product')->with(['id'=> $id]);
    }


public function updateproduct(Request $request, $id) 
    { 
        $employee = DB::table('product')
        ->where('id', $id)
        ->get();
        foreach ($employee as $vp );
        $status  = $vp->status; 

        if($status=="Enable")
        {
            Addproduct::where('id', $id)
            ->update([
                'status' => 'Disable'
             ]);
            }

            if($status=="Disable")
        {
            Addproduct::where('id', $id)
            ->update([
                'status' => 'Enable'
             ]);
            }

        return back()->with(['success'=>'Status Updated successfully.']); 

    }

        public function delete(Request $request)
    {
      $cityId = $request->sc_id;
      
      // TODO: Check for validation
      
      DB::table('product')->where('id', $cityId)-> delete();
      DB::table('product_images')->where('p_id', $cityId)-> delete();
      
        return back()->with('success','Product deleted successfully');
    }


public function updtuquery(Request $request, $id) 
    { 
        $employee = DB::table('admn_qery')
        ->where('id', $id)
        ->get();
        foreach ($employee as $vp );
        $status  = $vp->status; 

        if($status=="Pending..")
        {
            Adminquery::where('id', $id)
            ->update([
                'status' => 'Approved'
             ]);
            }

            if($status=="Approved")
        {
            Adminquery::where('id', $id)
            ->update([
                'status' => 'Pending..'
             ]);
            }

        return back()->with(['success'=>'Status Updated successfully.']); 

    }

     public function deleteuquery(Request $request)
    {
      $cityId = $request->sc_id;
      
      // TODO: Check for validation
      
      DB::table('admn_qery')->where('id', $cityId)-> delete();
      return back()->with('success','Query deleted successfully');
    }

}
