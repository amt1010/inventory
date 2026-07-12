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
<h4>Query List</h4>
<h6>Check status of uour query</h6>
</div>
<div class="page-btn">
<a href="{{ url('/admnquery') }}" class="btn btn-added"><img src="img/icons/plus.svg" alt="img" class="me-2">Add New Query</a>
</div>
</div>
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
<div class="card">
<div class="card-body">
<div class="table-top">
<div class="search-set">
<div class="search-path">
<a class="btn btn-filter" id="filter_search">
<img src="img/icons/filter.svg" alt="img">
<span><img src="img/icons/closes.svg" alt="img"></span>
</a>
</div>
<div class="search-input">
<a class="btn btn-searchset">
<img src="img/icons/search-white.svg" alt="img">
</a>
</div>
</div>
<div class="wordset">

</div>
</div>

<div class="table-responsive">
<table class="table  datanew">
<thead>
<tr>
<th>Name</th>
<th>Code</th>
<th>Query For</th>
<th>Status</th>
</tr>
</thead>
<tbody>
  

<?php
           $id= Auth::user()->id;

            use Illuminate\Support\Facades\DB;
            use App\Models\Product;
            $products = DB::table('admn_qery')
                           ->where('user_id', $id)
                           ->get();
            ?>
        @foreach ($products as $product) 
<tr>


<td>{{ $product->name }} </td>
<td>{{ $product->code }} </td>
<td>{{ $product->query_for }} </td>
<td>{{ $product->status }} </td>

</tr>

 @endforeach

</tbody>
</table>
</div>
</div>
</div>

</div>
</div>

</div>  




 <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
<script src="http://code.jquery.com/jquery-latest.min.js" type="text/javascript"></script>

<script>
   $('body').on('click', '#editsubcatgry', function (event) {
  event.preventDefault();
    var id = $(this).data('id');
    console.log(id)
    $.get('color/' + id + '/edit', function (data) {
         $('#userCrudModal').html("Edit category");
         $('#submit').val("Edit category");
         $('#practice_modal').modal('show');
         $('#color_id').val(data.data.id);
         $('#name').val(data.data.name);
         $('#catgry_code').val(data.data.code);
         $('#status').val(data.data.status);
     })
});
</script>

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


