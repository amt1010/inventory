<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0">
<meta name="description" content="POS - Bootstrap Admin Template">
<meta name="keywords" content="admin, estimates, bootstrap, business, corporate, creative, invoice, html5, responsive, Projects">
<meta name="author" content="Dreamguys - Bootstrap Admin Template">
<meta name="robots" content="noindex, nofollow">
<title>Inventory Solutions</title>

<link rel="shortcut icon" type="image/x-icon" href="img/favicon.png">

<link rel="stylesheet" href="{{ asset('css/bootstrap.min.css') }}">

<link rel="stylesheet" href="{{ asset('css/animate.css') }}">

<link rel="stylesheet" href="{{ asset('plugins/select2/css/select2.min.css') }}">

<link rel="stylesheet" href="{{ asset('css/dataTables.bootstrap4.min.css') }}">

<link rel="stylesheet" href="{{ asset('plugins/fontawesome/css/fontawesome.min.css') }}">
<link rel="stylesheet" href="{{ asset('plugins/fontawesome/css/all.min.css') }}">

<link rel="stylesheet" href="{{ asset('css/style.css') }}">
</head>


<body>
    @extends('Employe.layouts.app')

<div class="main-wrapper">

    <!-----------------------------left_menu--------------------->

               @include('Employe.layouts.top_menu')

    <!-----------------------------left_menu---------------------> 

    <!-----------------------------left_menu--------------------->

                @include('Employe.layouts.left_menu')

    <!-----------------------------left_menu---------------------> 

               
  <div class="page-wrapper">
<div class="content">
<div class="page-header">
<div class="page-title">
<h4>User Management</h4>
<h6>Add/Update User</h6>
</div>
</div>

<div class="card">
<div class="card-body">

 @if ($errors->any())
            <div class="alert alert-danger" role="alert">
                <ul>
                    @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
            @endif
            @if (Session::has('success'))
            <div class="alert alert-success text-center">
                <p style="font-size: 21px; font-weight: 1000; color: #ff0000;">{{ Session::get('success') }}</p>
            </div>
            @endif
<form class="forms-sample" method="POST" action="{{url('Add-EMP')}}" enctype="multipart/form-data"> 
  {{ csrf_field() }}

<div class="row">
<div class="col-lg-3 col-sm-6 col-12">
<div class="form-group">
<label>User Name</label>
<input  type="text" id="name" name="name" autocomplete="off" required="require">
</div>
</div>

<div class="col-lg-3 col-sm-6 col-12">
<div class="form-group">
<label>Email</label>
<input  type="text" id="email" name="email" autocomplete="off" required="require">
</div>
</div>

<div class="col-lg-3 col-sm-6 col-12">
<div class="form-group">
<label>Phone</label>
<input  type="text" id="phone" name="phone" autocomplete="off" required="require">
</div>
</div>

<div class="col-lg-3 col-sm-6 col-12">
<div class="form-group">
<label>Designation</label>
<input  type="text" id="design" name="design" autocomplete="off" required="require">
</div>
</div>

<div class="col-lg-4 col-sm-6 col-12">
<div class="form-group">
<label>Factory Name</label>
<input  type="text" id="factry_nm" name="factry_nm" autocomplete="off" required="require">
</div>
</div>

<div class="col-lg-8 col-sm-6 col-12">
<div class="form-group">
<label>Factory Detail</label>
<input type="text" id="factory_detl" name="factory_detl" autocomplete="off" required="require">
</div>
</div>

<div class="col-lg-3 col-sm-6 col-12">
<div class="form-group">
<label>Choose State</label>
<select class="select" id="state" name="state" required="require">
                <option selected value disabled>States Name</option>
                           
                           <?php
            use Illuminate\Support\Facades\DB;
            
            $states = DB::table('states')->get();
            ?>
                            @foreach ($states as $data)
                            <option value="{{$data->name}}">
                                {{$data->name}}
                            </option>
                            @endforeach

                        </select>
</div>
</div>

<div class="col-lg-3 col-sm-6 col-12">
<div class="form-group">
<label>City</label>
<input  type="text" id="city" name="city" autocomplete="off" required="require">
</div>
</div>

<div class="col-lg-6 col-12">
<div class="form-group">
<label>Address</label>
<input  type="text" id="addrss" name="addrss" autocomplete="off" required="require">
</div>
</div>

<div class="col-lg-12">
<div class="form-group">
<label> Avatar</label>
<div class="image-upload">
<input type="file" id="image" name="image">
<div class="image-uploads">
<img src="img/icons/upload.svg" alt="img">
<h4>Drag and drop a file to upload</h4>
</div>
</div>
</div>
</div>

<div class="col-lg-12">
<button type="submit" class="btn btn-submit me-2" name="submitt" id="submitt">Add</button>
<a href="javascript:void(0);" class="btn btn-cancel">Cancel</a>
</div>

</div>
</form>

</div>
</div>

</div>
</div> 


</div>    
<script src="js/jquery-3.6.0.min.js"></script>

<script src="js/feather.min.js"></script>

<script src="js/jquery.slimscroll.min.js"></script>

<script src="js/jquery.dataTables.min.js"></script>
<script src="js/dataTables.bootstrap4.min.js"></script>

<script src="js/bootstrap.bundle.min.js"></script>

<script src="plugins/select2/js/select2.min.js"></script>

<script src="plugins/sweetalert/sweetalert2.all.min.js"></script>
<script src="plugins/sweetalert/sweetalerts.min.js"></script>


<script src="js/script.js"></script>
    </body>


