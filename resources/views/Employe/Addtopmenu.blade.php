      
<!DOCTYPE html>
<html dir="ltr" lang="en">

<head>
  <title>NEWS</title>
  <meta charset="utf-8">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.7.0/css/all.css" integrity="sha384-lZN37f5QGtY3VHgisS14W3ExzMWZxybE1SJSEsQp9S+oqd12jhcu+A56Ebc1zFSJ" crossorigin="anonymous">

    <link href="https://demo.dashboardpack.com/architectui-html-free/main.css" rel="stylesheet">
    <!---slider-css--->
   
  
    <!----bootstrap----->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!---costum-css---->
    <link href="{{ asset('css/style.css') }}" rel="stylesheet">
    
    <script src="js/jquery-3.6.0.min.js"></script>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
</head>
<body>
    @extends('layouts.app')
<div class="app-container app-theme-white body-tabs-shadow  fixed-header">
     <!-----------------------------left_menu--------------------->

               @include('layouts.top_menu')

    <!-----------------------------left_menu---------------------> 

 
      <div class="ui-theme-settings">
         
      </div>
      <div class="app-main">
         <div class="app-sidebar sidebar-shadow">
            <div class="app-header__logo">
               <div class="logo-src"></div>
               <div class="header__pane ml-auto">
                  <div>
                     <button type="button" class="hamburger close-sidebar-btn hamburger--elastic" data-class="closed-sidebar">
                     <span class="hamburger-box">
                     <span class="hamburger-inner"></span>
                     </span>
                     </button>
                  </div>
               </div>
            </div>
            <div class="app-header__mobile-menu">
               <div>
               <button type="button" class="hamburger hamburger--elastic mobile-toggle-nav">
                  <span class="hamburger-box">
                  <span class="hamburger-inner"></span>
                  </span>
                  </button>
               </div>
            </div>
               
  <!-----------------------------left_menu--------------------->

                @include('layouts.left_menu')

  <!-----------------------------left_menu---------------------> 

               
   
          </div>
         <div class="app-main__outer">
                         <div class="group-form  mx-4">
          <h4 class="text-center">Add Top Menu</h4>



  <form class="forms-sample" method="POST" action="{{url('Add-tMenu')}}" enctype="multipart/form-data"> 
  {{ csrf_field() }}
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

 


  <div class="Services">
    <label for="name" class="form-label">Top Menu:</label>
    <input  type="text" id="menu" name="menu" placeholder="Add Top Menu" 
    autocomplete="off" required="require" class="form-control">
                  </div>
            

 <div class="two-btn" style="margin-top: 2%;">
               <button type="submit" class="submit-btn me-4 active" name="submitt" id="submitt">Add</button>
               <!-- <button type="reset" class="cancel-btn ">Cancel</button> -->
            </div>
          </form>
          <table class="table table-bordered">
        <tr>
           
            <th>Name</th>
            <th width="300px">Edit</th>
            <th width="300px">Delete</th>
        </tr>
        <?php
            use Illuminate\Support\Facades\DB;
            use App\Models\Topmenu;
            $Topmenus = DB::table('frst_menu')->get();
            ?>
        @foreach ($Topmenus as $Topmenu)
        <tr>
            
           
            <td>{{ $Topmenu->name }}</td>
            <td>
    <a href="" id="editcatgry" data-toggle="modal" 
    data-target='#cat_model' data-id="{{ $Topmenu->id }}" 
    class="btn btn-primary">Edit</a>
            </td>
            <td>
    <form action="{{ route('topmenu.delete', ['tid' => $Topmenu->id]) }}" method="POST">
      
                   
       
                   
      
                    @csrf
                    @method('DELETE')
                    <input type="text" name="cityId" hidden value="{{ $Topmenu->id }}">
                    <button type="submit" class="btn btn-danger">Delete</button>
                </form>
            </td>
        </tr>
        @endforeach
    </table>       
        </div>
    
    
          
            
         
<!-----------------------------left_menu--------------------->

              @include('layouts.footer')

<!-----------------------------left_menu--------------------->
</div></div>
<div class="modal fade" id="cat_model"  tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
                        <div class="modal-dialog">
                        <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLabel">Edit Form</h5>
        
      </div>
        <div class="modal-body">
        <form id="companydata">
        
<input type="hidden" id="color_id" name="color_id" value="">
<label style="margin-left: 20%;">Top Menu : </label>
<input type="text" name="name" id="name" value="" autocomplete="off">
    
    
                            
                            <div class="modal-footer">

<button type="submit" value="Submit" class="btn btn-success" 
id="submit" name="edit_data">Update</button>
      </div>
                           </form>
                           </div>
    </div>
                        </div>
                    </div>            
        <!--Custom JavaScript -->
    <script src="https://kit.fontawesome.com/201ed92cd4.js" crossorigin="anonymous"></script>
    <script type="text/javascript" src="https://demo.dashboardpack.com/architectui-html-free/assets/scripts/main.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
    </html>

    <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.9.1/jquery.js"></script>  
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@9"></script>
<script>

$(document).ready(function () {

    $.ajaxSetup({
  headers: {
    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
  }
});

$('body').on('click', '#submit', function (event) {
    event.preventDefault()
    var id = $("#color_id").val();
    var name = $("#name").val();
   
    $.ajax({
      url: 'tmenu/' + id,
      type: "POST",
      data: {
        id: id,
        name: name,
      },
      dataType: 'json',
      success: function (data) {
          
          $('#companydata').trigger("reset");
          $('#cat_model').modal('hide');
          window.location.reload(true);
      }
  });
});

$('body').on('click', '#editcatgry', function (event) {

    event.preventDefault();
    var id = $(this).data('id');
    console.log(id)
    $.get('tmenu/' + id + '/edit', function (data) {
         $('#userCrudModal').html("Edit category");
         $('#submit').val("Edit category");
         $('#cat_model').modal('show');
         $('#color_id').val(data.data.id);
         $('#name').val(data.data.name);
     })
});

}); 
</script>


