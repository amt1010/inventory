      
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
    @extends('include.app')
<div class="app-container app-theme-white body-tabs-shadow  fixed-header">
     <!-----------------------------left_menu--------------------->

               @include('include.top_menu')

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

                @include('include.left_menu')

  <!-----------------------------left_menu---------------------> 

               
   
          </div>
         <div class="app-main__outer">
         <div class="group-form  mx-4" style="font-weight:700;">
              
         <h4 class="text-center">Add Post</h4>



<form class="forms-sample" method="POST" action="{{url('Add-news')}}" enctype="multipart/form-data"> 
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

          @php $emp_type = Auth::user()->emp_type; @endphp
          

          <div class="group-form-felid row ">
          <div class="Services">
          <label for="name" class="form-label">Subcategory:</label>
    <select class="form-select " id="sctgry" name="sctgry"autocomplete="off">
              <option selected value disabled>------ Select Subcategory ------</option>
                         
                         <?php
          use Illuminate\Support\Facades\DB;
          
          $states = DB::table('submenu')
                        ->where('m_name', '=', $emp_type)
                        ->get();
          ?>
                          @foreach ($states as $data)
                          <option value="{{$data->id}}">
                              {{$data->name}}
                          </option>
                          @endforeach

                      </select>
                   </div>

                  <div class="Services">
  
  <input type="hidden" class="form-control" id="cate" name="cate" value="{{ $emp_type }}">
  
                     </div>
  </div>
  



  <div class="Servic" style="margin-top:2%;">
    <label for="name" class="form-label">Post Title:</label>
    <input  type="text" id="Title" name="Title" placeholder="Add Title" 
    autocomplete="off" required="require" class="form-control">
                  </div>

    <div class="Servic" style="margin-top:2%;">
    <label for="name" class="form-label">Description:</label>
    <textarea id="ndescrp" name="ndescrp" style="width:880px; height:280px;"></textarea>

    </div>
 <input  type="text" id="e_id" name="e_id" hidden  value="{{Auth::user()->id;}}" class="form-control">


          <div class="group-form-felid row mt-2">

    <div class="Services">
    <label for="name" class="form-label">Image Upload:</label>
    <input  type="file" id="image" name="image" class="form-control">
  </div>
     <div class="Services">
    <label for="name" class="form-label">News Time:</label>
    <input  type="datetime-local" id="ntime" name="ntime" class="form-control">
                  </div>

</div>
<div class="two-btn" style="margin-top: 2%;">
             <button type="submit" class="submit-btn me-4 active" name="submit" id="submit">Publish</button>
             <!-- <button type="reset" class="cancel-btn ">Cancel</button> -->
          </div>
        </form>      
                  </div>
              
            
         
<!-----------------------------left_menu--------------------->

              @include('include.footer')

<!-----------------------------left_menu--------------------->
</div></div>
            
        <!--Custom JavaScript -->
    <script src="https://kit.fontawesome.com/201ed92cd4.js" crossorigin="anonymous"></script>
    <script type="text/javascript" src="https://demo.dashboardpack.com/architectui-html-free/assets/scripts/main.js"></script>
    <script src="https://js.nicedit.com/nicEdit-latest.js" type="text/javascript"></script>
<script type="text/javascript">bkLib.onDomLoaded(nicEditors.allTextAreas);</script>
</body>
    </html>


