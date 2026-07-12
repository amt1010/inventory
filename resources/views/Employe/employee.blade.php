      
<!DOCTYPE html>
<html dir="ltr" lang="en">

<head>
  <title>Mysmsae</title>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.7.0/css/all.css" integrity="sha384-lZN37f5QGtY3VHgisS14W3ExzMWZxybE1SJSEsQp9S+oqd12jhcu+A56Ebc1zFSJ" crossorigin="anonymous">

    <link href="https://demo.dashboardpack.com/architectui-html-free/main.css" rel="stylesheet">
    <!---slider-css--->
    <link rel="stylesheet" type="text/css" href="css/slick.css">
    <link rel="stylesheet" type="text/css" href="css/slick-theme.css">
  
    <!----bootstrap----->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!---costum-css---->
    <link href="{{ asset('css/style.css') }}" rel="stylesheet">
    <link href="css/media.css" rel="stylesheet">
    <script src="js/jquery-3.6.0.min.js"></script>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
</head>
<body>
    @extends('Employe.layouts.app')
<div class="app-container app-theme-white body-tabs-shadow  fixed-header">
     <!-----------------------------left_menu--------------------->

               @include('Employe.layouts.top_menu')

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

                @include('Employe.layouts.left_menu')

  <!-----------------------------left_menu---------------------> 

               
   
          </div>
         <div class="app-main__outer">
                         <div class="group-form  mx-4">
          <h4 class="text-center">Add Employee</h4>



  <form class="forms-sample" method="POST" action="{{url('Add-EMP')}}" enctype="multipart/form-data"> 
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
                <p style="font-size: 28px; font-weight: 1000; color: #ff0000;">{{ Session::get('success') }}</p>
            </div>
            @endif

 <div class="group-form-felid">
                 <div class="Services">
                       <label class="form-label">Employee Category</label>
                     
                       <select class="form-select " id="Type" name="Type"autocomplete="off">
              <option selected value disabled>------ Select Category ------</option>
                         
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
                <div class="Services">
                     <label for="name" class="form-label">Full Name</label>
            <input  type="text" id="fn" name="fn" placeholder="Enter Full Name"
             autocomplete="off" required="require" class="form-control" >
                </div>
               </div>

         

              

 <div class="group-form-felid" style="margin-top: 2%;">
                  <div class="Services">
                     <label for="name" class="form-label">Email Id</label>
                     <input  type="email" id="email" name="email" placeholder="Enter Email Id" autocomplete="off" required="require" class="form-control">
                  </div>
                 <div class="Services">
                     <label for="name" class="form-label">Contact No.</label>
                     <input  type="text" id="phone" name="phone" placeholder="Enter Contact No." autocomplete="off" required="require" class="form-control">
                  </div>
                </div>


 <div class="group-form-felid" style="margin-top: 2%;">
                  <div class="Services">
                     <label for="Password" class="form-label">Password</label>
                     <input  type="text" id="pass" name="pass" placeholder="Enter Password" autocomplete="off" required="require" class="form-control">
                  </div>
                 <div class="Services">
                     <label for="name" class="form-label">Confirm Password</label>
                     <input  type="password" id="cpass" name="cpass" placeholder="Enter Confirm Password" autocomplete="off" required="require" class="form-control">
                  </div>
                </div>


  <div class="group-form-felid" style="margin-top: 2%;">
                  
                  <div class="Services">
                     <label for="image" class="form-label">Image</label>
               <input type="file" name="image" id="image" placeholder="Upload image" class="form-control file-upload-default">
                  </div>
                </div>
 <div class="Add" style="margin-top: 2%; margin-bottom: 2%;">
               <label for="location" class="form-label">Full Address</label>
               <textarea class="form-control" id="location" name="location" rows="2"></textarea>
             </div>
             


 <div class="two-btn" style="margin-top: 2%;">
               <button type="submit" class="submit-btn me-4 active" name="submit" id="submit">Add</button>
               <!-- <button type="reset" class="cancel-btn ">Cancel</button> -->
            </div>
          </form>
        </div>
    
              
            
         
<!-----------------------------left_menu--------------------->

              @include('Employe.layouts.footer')

<!-----------------------------left_menu--------------------->
</div></div>
            
        <!--Custom JavaScript -->
    <script src="https://kit.fontawesome.com/201ed92cd4.js" crossorigin="anonymous"></script>
    <script type="text/javascript" src="https://demo.dashboardpack.com/architectui-html-free/assets/scripts/main.js"></script>

</body>
    </html>
<script>
$(document).ready(function() {
  $('#fn').focusout(function(){
    $('#fn').filter(function(){
      var email = $('#fn').val();
      var emailReg = /^[a-zA-z _]*$/;
      if ( !emailReg.test( email ) ) {
        alert('Only alphabets and whitespace are allowed.');
      } else {
        //alert('Thank you for your valid email');
      }
    });
  }); 

});
</script>
<script type="text/javascript" >
$(function() {
   $("#phone").bind("keydown", function (e) {
        if ((e.keyCode >= 48 && e.keyCode <= 57) || (e.keyCode >= 96 && e.keyCode <= 105)) {
             // 0-9
            var val = $(this).val();
            if (!val.match(/^\d{10}$/))
            {
               //console.log("it is a number but nut match 10 digit")
            }
            else
            {
               //alert("Only 10 Digit Valid");
               return false; // to restrict user to not enter more than 10 digit
            }
        }
        else
        {
           if(e.keyCode == 8)
              return true; // backspace
           //alert("Only Valid Numeric Value");
                event.preventDefault();
                return false;
        }
   });   
});


</script>



