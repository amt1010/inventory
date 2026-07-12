<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Allnews;
use Image;
class AddnewsController extends Controller
{
    public function addnews(Request $req)
    {
        
    $req->validate([
        'image' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        'subcategory' => 'nullable',
       ]);

//$scate = implode(",", $req->get('scate'));
       
    $user= new Allnews; 
    $user->category= $req->cate;
    $user->subcategory= $req->sctgry;
    $user->title= $req->Title;
    $user->description= $req->ndescrp; 
    $user->time= $req->ntime;
    $user->emp_id= $req->e_id;
    $user->status= 'Enable';
   // $user->view= 0;
 
    $image = $req->image;
    $imageName = time().'.'.$image->extension();
     
        $img = Image::make($image->path());
        $img->resize(150, 150, function ($constraint) {
            $constraint->aspectRatio();
        })->save(public_path('image/thumbnail').'/'.$imageName);
   
        $imgg = Image::make($image->path());
        $imgg->resize(543, 319, function ($constraint) {
            $constraint->aspectRatio();
        })->save(public_path('image/news_image').'/'.$imageName);

   
       $user->image= $imageName;
    


      echo $user->save();
      return back()->with('success', 'News Added Successfully...!');  
    }
}
