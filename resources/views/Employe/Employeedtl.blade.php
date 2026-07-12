      
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


         <div class="app-main__outer" style="margin-top: 5%;">
         <div class="mx-4">
               
         <section class="section about-section gray-bg" id="about">
            <div class="container">
            <?php
           use App\Models\Allnews;
           $products = DB::table('users')->where(function ($query) use ($id) {
               $query->where('id', '=', $id);
           })->get();
           foreach ($products as $post );

           ?>

@foreach ($products as $post )
   <div class="row align-items-center flex-row-reverse">
       <div class="col-lg-7">
           <div class="about-text go-to"  style="margin-left:-10%;">
     <h3 class="dark-color ml-3">{{ $post->emp_name }}</h3>
    
     @endforeach
     <?php $kTsp = (new DateTime)->format('Y-m-d');
//echo $kTsp; ?>
                            <div class="row about-list mt-2">
                            <div class="col-md-6" style="background-color:pink; padding:25px; border-radius:15%;">
<h4 style="font-size:38px; text-align:center;">Today's News</h4>
<h3 style="font-size:40px; text-align:center;">{{$counts = Allnews::whereDate('created_at',$kTsp)
                                                                  ->where('emp_id',$id)
                                                                  ->count();}}</h3>
                                    
                                </div>

                                <div class="col-md-5" style="background-color:pink; padding:25px; border-radius:15%; margin-left:2%;">
<h4 style="font-size:38px; text-align:center;">Total News</h4>
<h3 style="font-size:40px; text-align:center;"> {{$count = Allnews::where('emp_id',$id)->count();}}</h3>
                                    
                                </div>

                            </div>
                        </div>
                    </div>
                    <div class="col-lg-5">
                        <div class="about-avatar" style="margin-left: 5%;">
               <img src="../image/employee_image/{{ $post->image }}" height="220" width="220" style=" border: 4px solid black; border-radius:22%;">
                        </div>
                    </div>
                </div>
              

              
            </div>
        </section> 
                  </div>
              
            
         
<!-----------------------------left_menu--------------------->

              @include('Employe.layouts.footer')

<!-----------------------------left_menu--------------------->
</div></div>
<style>
   body{
    color: #6F8BA4;
    margin-top:20px;
}
.section {
    padding: 100px 0;
    position: relative;
}
.gray-bg {
    background-color: #f5f5f5;
}
img {
    max-width: 100%;
}
img {
    vertical-align: middle;
    border-style: none;
}
/* About Me 
---------------------*/
.about-text h3 {
  font-size: 45px;
  font-weight: 700;
  margin: 0 0 6px;

}
@media (max-width: 767px) {
  .about-text h3 {
    font-size: 35px;
  }
}


@media (max-width: 767px) {
  .about-text h6 {
    font-size: 18px;
  }
}




@media (max-width: 991px) {
  .about-avatar {
    margin-top: 30px;
  }
}



</style>          
        <!--Custom JavaScript -->
    <script src="https://kit.fontawesome.com/201ed92cd4.js" crossorigin="anonymous"></script>
    <script type="text/javascript" src="https://demo.dashboardpack.com/architectui-html-free/assets/scripts/main.js"></script>

</body>
    </html>




