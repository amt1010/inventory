<div class="sidebar" id="sidebar">
<div class="sidebar-inner slimscroll">
<div id="sidebar-menu" class="sidebar-menu">
<ul>
<li class="active">
<a href="{{ url('/empDash') }}"><img src="img/icons/dashboard.svg" alt="img"><span> Dashboard</span> </a>
</li>

<li class="submenu">
<a href="javascript:void(0);"><img src="img/icons/product.svg" alt="img"><span> Product</span> <span class="menu-arrow"></span></a>
<ul>

<li><a href="{{ url('/userProductlist') }}">Product List</a></li>
<li><a href="{{ url('/useraddProduct') }}">Add Product</a></li>

</ul>
</li>

<li class="submenu">
<a href="javascript:void(0);"><img src="img/icons/purchase1.svg" alt="img"><span> Query For Admin</span> <span class="menu-arrow"></span></a>
<ul>

<li><a href="{{ url('/viewadmnquery') }}">View Query</a></li>
<li><a href="{{ url('/admnquery') }}">Add Query</a></li>

</ul>
</li>


</ul>
</div>
</div>
</div>
