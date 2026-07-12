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
<h4>Product Third Category list</h4>
<h6>View/Search product Category</h6>
</div>
<div class="page-btn">
<a href="{{ url('/addthrdcategory') }}" class="btn btn-added">
<img src="img/icons/plus.svg" class="me-1" alt="img">Add Third Category
</a>
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
<a class="btn btn-searchset"><img src="img/icons/search-white.svg" alt="img"></a>
</div>
</div>
<div class="wordset">
<ul>
<li>
<a data-bs-toggle="tooltip" data-bs-placement="top" title="pdf"><img src="img/icons/pdf.svg" alt="img"></a>
</li>
<li>
<a data-bs-toggle="tooltip" data-bs-placement="top" title="excel"><img src="img/icons/excel.svg" alt="img"></a>
</li>
<li>
<a data-bs-toggle="tooltip" data-bs-placement="top" title="print"><img src="img/icons/printer.svg" alt="img"></a>
</li>
</ul>
</div>
</div>


<div class="table-responsive">
<table class="table  datanew">
<thead>
<tr>
<th>
<label class="checkboxs">
<input type="checkbox" id="select-all">
<span class="checkmarks"></span>
</label>
</th>
<th>Third Category name</th>
<th>Subcategory name</th>
<th>Category name</th>
<th>Status</th>
<th>Edit</th>
<th>Delete</th>
</tr>
</thead>
<tbody>


 <?php
            use Illuminate\Support\Facades\DB;
            use App\Models\Product;
            use App\Models\Submenu;
            $products = DB::table('thirdmenu')->get();
            ?>
        @foreach ($products as $product)    
<tr>
<td>
<label class="checkboxs">
<input type="checkbox">
<span class="checkmarks"></span>
</label>
</td>
<td>{{ $product->name }}</td>
<?php
$vid = $product->sm_id;
  $vipu =Submenu::when($vid, function ($query,$vid ) {
     return $query->where('id', $vid);})->get();
     foreach ($vipu as $vppu );
 
 $avacardou=$vppu->name;
    print_r($avacardou); ?>
 @foreach ($vipu as $vppu)
<td>{{ $vppu->name }}</td>

<?php
$cid = $product->m_id;
  $vip =Product::when($cid, function ($query,$cid ) {
     return $query->where('id', $cid);})->get();
     foreach ($vip as $vpp );
 
 $avacardo=$vpp->p_city;
    print_r($avacardo); ?>
 @foreach ($vip as $vpp)
<td>{{ $vpp->name }}</td>

<td>{{ $product->status }}</td>
<td>
<a class="me-3" id="editsubcatgry"  data-id="{{ $product->id }}">
<img src="img/icons/edit.svg" alt="img">
</a>
</td>
<td>
    <form class="forms-sample" method="POST" action="{{url('deletedsubcatv')}}" enctype="multipart/form-data">
                  @csrf
                  @method('post')
  <input type="hidden" id="sc_id" name="sc_id" value="{{ $product->id }}">
<button type="submit" class="btn default"><img src="img/icons/delete.svg" alt="img"></button>
                </form>
</td>
</tr>
 @endforeach
 @endforeach
 @endforeach


</tbody>
</table>
</div>
</div>
</div>

</div>
</div>

</div>
<div class="modal fade" id="practice_modal"  tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
                        <div class="modal-dialog">
                        <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLabel">Edit Form</h5>
        
      </div>
        <div class="modal-body">
<form class="forms-sample" method="POST" action="{{url('vssubcategoryv')}}" enctype="multipart/form-data">        

                            @csrf
                            @method('post')

<input type="hidden" id="color_id" name="color_id" value="">
<label style="margin-left: 0%;">Third Category Name: </label>
<input type="text" name="name" id="name" value="" autocomplete="off">
<br>

<label class="mt-3" style="margin-left: 0%;">Category Status: </label>
<input type="text" name="status" id="status" value="" autocomplete="off">
    
    
                            
                            <div class="modal-footer">

<button type="submit" value="Submit" class="btn btn-success" 
id="submit" name="edit_data">Update</button>
      </div>
                           </form>
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
    $.get('colorv/' + id + '/edit', function (data) {
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


