<div class="sidebar" id="sidebar">
<div class="sidebar-inner slimscroll">
<div id="sidebar-menu" class="sidebar-menu">
<ul>
<li class="active">
<a href="{{ url('/emphome') }}"><img src="img/icons/dashboard.svg" alt="img"><span> Dashboard</span> </a>
</li>
<li class="submenu">
<a href="javascript:void(0);"><img src="img/icons/product.svg" alt="img"><span> Category</span> <span class="menu-arrow"></span></a>
<ul>

<li><a href="{{ url('/categorylist') }}">Category List</a></li>
<li><a href="{{ url('/addcategory') }}">Add Category</a></li>
<li><a href="{{ url('/subcategorylist') }}">Sub Category List</a></li>
<li><a href="{{ url('/addsubcategory') }}">Add Sub Category</a></li>
<li><a href="{{ url('/thrdcategorylist') }}">Third Label Category List</a></li>
<li><a href="{{ url('/addthrdcategory') }}">Add Third Label Category</a></li>

</ul>
</li>




<li class="submenu">
<a href="javascript:void(0);"><img src="img/icons/users1.svg" alt="img"><span> People</span> <span class="menu-arrow"></span></a>
<ul>

<li><a href="{{ url('/userlist') }}">User List</a></li>
<li><a href="{{ url('/adduser') }}">Add User</a></li>

</ul>
</li> 


<li class="submenu">
<a href="javascript:void(0);"><img src="img/icons/product.svg" alt="img"><span> Product</span> <span class="menu-arrow"></span></a>
<ul>

<li><a href="{{ url('/Productlist') }}">Product List</a></li>
<li><a href="{{ url('/addProduct') }}">Add Product</a></li>

</ul>
</li>

<li>
<a href="{{ url('/viewuserquery') }}"><img src="img/icons/purchase1.svg" alt="img"><span> User Query</span> </a>
</li>

</ul>
</div>
</div>
</div>
