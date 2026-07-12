<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Validator;
use Response;
use Redirect;
use App\Models\Submenu;
use App\Models\thirdmenu;

class DropdownController extends Controller
{
    public function fetchState(Request $request)
    {
        $data['submenu'] = Submenu::where("m_name",$request->country_id)->get(["name", "id"]);
        return response()->json($data);
    }

    public function fetchStatess(Request $request)
    {
        $data['thmenu'] = thirdmenu::where("sm_id",$request->country_id)->get(["name", "id"]);
        return response()->json($data);
    }
}
