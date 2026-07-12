<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Models\Allnews;
use App\Models\User;

class EditnewsController extends Controller
{
    public function Editnews($id){
        return view('Edit_news')->with(['id'=> $id]);
    }

    public function update(Request $request, $id)
    {
        $student = Allnews::find($id);

        $student->title = $request->input('Title');
        $student->description = $request->input('ndescrp');

        $student->update();
        return redirect('viewnews')->with('success','News Updated Successfully');
    }

    public function updateStatus(Request $request, $id) 
    { 
        $employee = DB::table('users')
        ->where('id', $id)
        ->get();
        foreach ($employee as $vp );
        $status  = $vp->status; 

        if($status=="Enable")
        {
            User::where('id', $id)
            ->update([
                'status' => 'Disable'
             ]);
            }

            if($status=="Disable")
        {
            User::where('id', $id)
            ->update([
                'status' => 'Enable'
             ]);
            }

        return redirect('userlist')->with(['success'=>'Status Updated successfully.']); 

    } 

    public function delete(Request $request)
    {
      $cityId = $request->sc_id;
      
      // TODO: Check for validation
      
      DB::table('users')->where('id', $cityId)-> delete();
      
      
        return back()->with('success','User deleted successfully');
    }


    

}
