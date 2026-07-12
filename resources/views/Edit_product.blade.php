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

<link rel="shortcut icon" type="image/x-icon" href="../img/favicon.png">

<link rel="stylesheet" href="{{ asset('css/bootstrap.min.css') }}">

<link rel="stylesheet" href="{{ asset('css/animate.css') }}">



<link rel="stylesheet" href="{{ asset('plugins/select2/css/select2.min.css') }}">

<link rel="stylesheet" href="{{ asset('css/dataTables.bootstrap4.min.css') }}">

<link rel="stylesheet" href="{{ asset('plugins/fontawesome/css/fontawesome.min.css') }}">
<link rel="stylesheet" href="{{ asset('plugins/fontawesome/css/all.min.css') }}">
<link rel="stylesheet" href="{{ asset('css/style.css') }}">
</head>


<body>

<div class="main-wrapper">

    <!-----------------------------left_menu--------------------->

              <div class="header">

<div class="header-left active">
<a href="index.html" class="logo">
<img src="../img/logo.png" alt="">
</a>
<a href="index.html" class="logo-small">
<img src="../img/logo-small.png" alt="">
</a>
<a id="toggle_btn" href="javascript:void(0);">
</a>
</div>

<a id="mobile_btn" class="mobile_btn" href="#sidebar">
<span class="bar-icon">
<span></span>
<span></span>
<span></span>
</span>
</a>

<ul class="nav user-menu">


<li class="nav-item">
<div class="top-nav-search">
<i style="font-size:20px" class="fas fa-envelope-open  pt-3"></i>
<span style="font-size:15px; font-weight: 700;">inventorysolutions000@gmail.com</span>

</div>
</li>


<li class="nav-item dropdown has-arrow flag-nav">
<a class="nav-link dropdown-toggle" data-bs-toggle="dropdown" href="javascript:void(0);" role="button">
<i style="font-size:24px" class="fas fa-phone-volume"></i>
<span style="font-size:15px; font-weight: 700;">+91 95369 06738</span>
</a>

</li>
</ul>


</div>


    <!-----------------------------left_menu---------------------> 

 <div class="content">
<div class="page-header">
<div class="page-title">
<h4>Product Add</h4>
<h6>Create new product</h6>
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
<form class="forms-sample" method="POST" action="{{url('Add-product')}}" enctype="multipart/form-data"> 
  {{ csrf_field() }}


<div class="row">
<div class="col-lg-3 col-sm-6 col-12">
<div class="form-group">
<label>Product Name</label>
<input  type="text" id="pname" name="pname" autocomplete="off" required="require">
</div>
</div>
<div class="col-lg-3 col-sm-6 col-12">
<div class="form-group">
<label>Category</label>
<select class="select" id="pcategry" name="pcategry" required="require">
                <option selected value disabled>Select Category</option>
                           
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
<div class="col-lg-3 col-sm-6 col-12">
<div class="form-group">
<label>Sub Category</label>
<select class="select" id="psubcategry" name="psubcategry" required="require">

</select>
</div>
</div>

<div class="col-lg-3 col-sm-6 col-12">
<div class="form-group">
<label>Third Category</label>
<select class="select" id="pthrcategry" name="pthrcategry" required="require">

</select>
</div>
</div>

<div class="col-lg-4 col-sm-6 col-12">
<div class="form-group">
<label>Product Company</label>
<input  type="text" id="pcompny" name="pcompny" autocomplete="off" required="require">
</div>
</div>

<div class="col-lg-4 col-sm-6 col-12">
<div class="form-group">
<label>Quantity</label>
<input  type="text" id="pquantity" name="pquantity" autocomplete="off" required="require">
</div>
</div>

<div class="col-lg-4 col-sm-6 col-12">
<div class="form-group">
<label>Price</label>
<input  type="text" id="pprice" name="pprice" autocomplete="off" required="require">
</div>
</div>

<div class="col-lg-12">
<div class="form-group">
<label>Description</label>
<textarea class="form-control" id="pdesc" name="pdesc"></textarea>
</div>
</div>




<div class="col-lg-12">
<div class="form-group">
            <table class="table table-bordered" id="dynamicAddRemove" >
                <tr>
                    <th class="col-lg-10 col-sm-8 col-6">Product Image</th>
                    <th class="col-lg-2 col-sm-4 col-6">Action</th>
                </tr>

<tr>
 <td>
   <div class="image-upload">
   <input type="file" id="image" name="image">
   <div class="image-uploads">
   <img src="img/icons/upload.svg" alt="img">
   <h4>Drag and drop a file to upload</h4>
   </div>
   </div>
 </td>

 <td>
    <button type="button" name="add" id="dynamic-ar" class="btn btn-outline-primary" style="color:#000000; font-weight:700;" >Add More Images</button>
 </td>

</tr>

            </table>
</div>
</div>



<div class="col-lg-12">
<button type="submit" class="btn btn-submit me-2" name="submitt" id="submitt">Add Product</button>
<a href="productlist.html" class="btn btn-cancel">Cancel</a>
</div>
</div>

</form>

</div>
</div>

</div>


</div>    

    </body>
    </html>


