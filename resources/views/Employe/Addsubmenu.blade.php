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
<h4>Product Add sub Category</h4>
<h6>Create new product sub Category</h6>
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

<form class="forms-sample" method="POST" action="{{url('Add-Smenu')}}" enctype="multipart/form-data"> 
  {{ csrf_field() }}
<div class="row">
<div class="col-lg-4 col-sm-6 col-12">
<div class="form-group">
<label>Select Category</label>
<select class="select" id="menu" name="menu" required="require">
                <option selected value disabled>Category Name</option>
                           
                           <?php
            use Illuminate\Support\Facades\DB;
            
            $states = DB::table('menu')->get();
            ?>
                            @foreach ($states as $data)
                            <option value="{{$data->id}}">
                                {{$data->name}}
                            </option>
                            @endforeach

                        </select>
</div>
</div>
<div class="col-lg-4 col-sm-6 col-12">
<div class="form-group">
<label>Subcategory Name</label>
<input  type="text" id="smenu" name="smenu" autocomplete="off" required="require">
</div>
</div>
<div class="col-lg-4 col-sm-6 col-12">
<div class="form-group">
<label>Subcategory Code</label>
<input  type="text" id="scatcode" name="scatcode" autocomplete="off" required="require">
</div>
</div>

<div class="col-lg-12">
<button type="submit" class="btn btn-submit me-2" name="submitt" id="submitt">Add</button>
<a href="subcategorylist.html" class="btn btn-cancel">Cancel</a>
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


