      
<!DOCTYPE html>
<html dir="ltr" lang="en">

<head>
  <title>NEWS</title>
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
          <h4 class="text-center" style="font-weight:700;">All Subscribers</h4>
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
          <div class="table-responsive pt-3">
              <table class="table table-responsive">
    
              <thead>
            <tr style="color: #0616be; font-size: 17px;">
            <th width="50%">IP Address</th>
            <th width="30%">Time</th>
            <th width="50%">Date</th>
            
           
            </tr>
            </thead>
            <tbody>
      
           
            <?php
            use Illuminate\Support\Facades\DB;
           
            $employee = DB::table('suscribe')->get();
            ?>
        @foreach ($employee as $post)
              
              
<tr style="font-weight: 700; font-size: 16px;">

         
            <td>{{ $post->ip_addrs }}</td>
            <td>{{ $post->time }}</td>
            <td>{{ $post->date }}</td>
             


</tr>
            @endforeach
          

            </tbody>
        </table>            
          </div>

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




