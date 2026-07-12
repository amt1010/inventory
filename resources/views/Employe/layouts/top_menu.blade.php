<div class="header">

<div class="header-left active">
<a href="index.html" class="logo">
<img src="img/logo.png" alt="">
</a>
<a href="index.html" class="logo-small">
<img src="img/logo-small.png" alt="">
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


<li class="nav-item dropdown has-arrow main-drop">
<a href="javascript:void(0);" class="dropdown-toggle nav-link userset" data-bs-toggle="dropdown">
<span class="user-img"><img src="img/profiles/avator1.jpg" alt="">
<span class="status online"></span></span>
</a>
<div class="dropdown-menu menu-drop-user">
<div class="profilename">
<div class="profileset">
<span class="user-img"><img src="img/profiles/avator1.jpg" alt="">
<span class="status online"></span></span>
<div class="profilesets">
<h6>John Doe</h6>
<h5>Admin</h5>
</div>
</div>
<hr class="m-0">
<a class="dropdown-item" href="profile.html"> <i class="me-2" data-feather="user"></i> My Profile</a>

<hr class="m-0">
<a class="dropdown-item logout pb-0" href="{{ route('logout') }}"><img src="img/icons/log-out.svg" class="me-2" alt="img">Logout</a>
</div>
</div>
</li>


</ul>


<div class="dropdown mobile-user-menu">
<a href="javascript:void(0);" class="nav-link dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false"><i class="fa fa-ellipsis-v"></i></a>
<div class="dropdown-menu dropdown-menu-right">
<a class="dropdown-item" href="profile.html">My Profile</a>

<a class="dropdown-item" href="{{ route('logout') }}">Logout</a>
</div>
</div>

</div>
