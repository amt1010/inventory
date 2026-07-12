<?php
namespace App\Http\Controllers\Home;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
class EmployeeHomeController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:employee');
    }
    public function index()
    {
        return view('home.employee');
    }
}