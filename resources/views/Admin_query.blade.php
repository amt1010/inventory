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

               @include('include.top_menu')

    <!-----------------------------left_menu---------------------> 

    <!-----------------------------left_menu--------------------->

                @include('include.left_menu')

    <!-----------------------------left_menu---------------------> 

               
   
<div class="page-wrapper">
<div class="content">
<div class="page-header">
<div class="page-title">
<h4>Product Add Category Query</h4>
<h6>Create New Category Query</h6>
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
<form class="forms-sample" method="POST" action="{{url('Add-query')}}" enctype="multipart/form-data"> 
  {{ csrf_field() }}
<div class="row">
<div class="col-lg-4 col-sm-6 col-12">
<div class="form-group">
<label>Query For</label>
<select class="select" id="query_for" name="query_for" required="require">
                <option selected value disabled>Select</option>
                <option value="Category">Category</option>
                <option value="SubCategory">SubCategory</option>
                <option value="Third Category">Third Category</option>
    </select>
</div>
</div>

<div class="col-lg-4 col-sm-6 col-12">
<div class="form-group">
<label>Name</label>
<input  type="text" id="menu" name="menu" autocomplete="off" required="require">
</div>
</div>
<input  type="hidden" id="user_id" name="user_id" value="{{Auth::user()->id; }}">

<div class="col-lg-4 col-sm-6 col-12">
<div class="form-group">
<label>Code</label>
 <input  type="text" id="code" name="code" autocomplete="off" required="require">
</div>
</div>

<div class="col-lg-12">
<div class="form-group">
<label>Reason</label>
<textarea class="form-control" id="reason" name="reason"></textarea>
</div>
</div>

<div class="col-lg-12">
    
<button type="submit" class="btn btn-submit me-2" name="submitt" id="submitt">Add</button>
<a href="categorylist.html" class="btn btn-cancel">Cancel</a>
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


