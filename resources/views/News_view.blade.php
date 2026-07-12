      
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
              
         <h4 class="text-center">View News</h4>

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


<div class="table-responsive">  
           <table class="table table-bordered">  
                <tr>  
                     <th width="10%">S.no.</th>  
                     <th width="15%">Image</th>
                     <th width="60%">Title</th>
                      
                     <th width="5%">STATUS</th> 
                     <th width="5%">EDIT</th>
                </tr>

 <?php

 
 $sn=1;

  $a= Auth::user()->id;
  //echo $a;
            use Illuminate\Support\Facades\DB;
            
            $employee = DB::table('all_news')
                      ->where('emp_id', $a)
                      ->orderBy('id', 'desc')
                      ->get();
            ?>
        @foreach ($employee as $post)

                <tr>  
             <td>{{$sn}}</td>
             <td><img src="image/news_image/{{ $post->image }}" style="height: 70px; width:100px; border-radius: 20%;"></td>
             <td>{{ $post->title }}</td>
    <td>
    <form action="{{ url('updtstatus/'.$post->id) }}" method="POST">
    @method('PUT')
    @csrf
<button type="submit" class="btn btn-info"  >{{ $post->status }}</button>
   </form>
   </td>

                        @php $ID = $post->id; @endphp
<td><a href="{{ url('get-id', ['id'=>$ID]) }}"><button type="button" name="update" class="btn btn-primary bt-xs update" >Edit</button></a></td>

    </tr>

    <?php $sn++;?>
     @endforeach
          
             </table>  
         </div>
   
                  </div>
              
            
         
<!-----------------------------left_menu--------------------->

              @include('include.footer')

<!-----------------------------left_menu--------------------->
</div></div>
            
        <!--Custom JavaScript -->
    <script src="https://kit.fontawesome.com/201ed92cd4.js" crossorigin="anonymous"></script>
    <script type="text/javascript" src="https://demo.dashboardpack.com/architectui-html-free/assets/scripts/main.js"></script>
    <script src="http://js.nicedit.com/nicEdit-latest.js" type="text/javascript"></script>
<script type="text/javascript">bkLib.onDomLoaded(nicEditors.allTextAreas);</script>
</body>
    </html>
