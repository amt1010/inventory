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
<h4>Product Details</h4>
<h6>Full details of a product</h6>
</div>
</div>

<?php
            use App\Models\Product;
            use App\Models\Submenu;
            use App\Models\Thirdmenu;

            $products = DB::table('product')->where(function ($query) use ($id) {
                $query->where('id', '=', $id);
            })->get();
            foreach ($products as $post );
?>

@foreach ($products as $post )

<div class="row mt-5">
    <div class="col-lg-3 col-sm-12">
<div class="card">
<div class="card-body">
<div class="slider-product-details">
<div class="owl-carousel owl-theme product-slide">

    <?php 

    $producty = DB::table('product_images')
                ->where('p_id',$id) 
                ->orderBy('id', 'asc')
                ->limit(2)
                ->get();

?>
@foreach ($producty as $postsy )
<div class="slider-product">

<img src="../image/product_image/{{ $postsy->image }}" alt="img">
</div>
@endforeach
</div>
</div>
</div>
</div>
</div>

<div class="col-lg-6 col-sm-12">
<div class="card">
<div class="card-body">

<div class="productdetails">
<ul class="product-bar">
<li>
<h4>Product</h4>
<h6>{{ $post->name }}</h6>
</li>
<li>
<h4>Category</h4>
<?php
$cid = $post->category;
  $vip =Product::when($cid, function ($query,$cid ) {
     return $query->where('id', $cid);})->get();
     foreach ($vip as $vpp );
 
 $avacardo=$vpp->p_city;
    print_r($avacardo); ?>
 @foreach ($vip as $vpp)
<h6>{{ $vpp->name }}</h6>
</li>
<li>
<h4>Sub Category</h4>
<?php
$vid = $post->subcategory;
  $vipu =Submenu::when($vid, function ($query,$vid ) {
     return $query->where('id', $vid);})->get();
     foreach ($vipu as $vppu );
?>
 @foreach ($vipu as $vppu)
<h6>{{ $vppu->name }}</h6>
</li>

<li>
<h4>Third Category</h4>
<?php
$vido = $post->thrdcatgry;
  $vipuo =Thirdmenu::when($vido, function ($query,$vido ) {
     return $query->where('id', $vido);})->get();
     foreach ($vipuo as $vppuo );
?>
 @foreach ($vipuo as $vppuo)
<h6>{{ $vppuo->name }}</h6>
</li>

<li>
<h4>Company</h4>
<h6>{{ $post->company }}</h6>
</li>
<li>
<h4>Quantity</h4>
<h6>{{ $post->quantity }}</h6>
</li>


<li>
<h4>Price</h4>
<h6>{{ $post->price }}</h6>
</li>
<li>
<h4>Status</h4>
<h6>{{ $post->status }}</h6>
</li>
<li>
<h4>Description</h4>
<h6>{{ $post->descrp }}</h6>
</li>
</ul>
</div>
</div>
</div>
</div>
<div class="col-lg-3 col-sm-12">
<div class="card">
<div class="card-body">
<div class="slider-product-details">
<div class="owl-carousel owl-theme product-slide">
<div class="slider-product mt-3">
<img src="../image/product_image/{{ $post->image }}" alt="img" style="height: 163px;">

</div>

<div class="slider-product mt-5">
    <?php 

    $product = DB::table('product_images')
                ->where('p_id',$id) 
                ->orderBy('id', 'desc')
                ->limit(1)
                ->get();

?>
@foreach ($product as $posts )
<img src="../image/product_image/{{ $posts->image }}" alt="img">
@endforeach
</div>
</div>
</div>
</div>
</div>
</div>
</div>

 
 @endforeach
 @endforeach
 @endforeach
 @endforeach


</div>

</div>    

    </body>
    </html>


