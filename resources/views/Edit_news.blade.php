      
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
              
         <h4 class="text-center">Add News</h4>


         <?php
           
           $products = DB::table('all_news')->where(function ($query) use ($id) {
               $query->where('id', '=', $id);
           })->get();
           foreach ($products as $post );

           ?>

@foreach ($products as $post )
<form class="forms-sample" method="POST" action="{{ url('update-student/'.$post->id) }}" enctype="multipart/form-data"> 
@method('PUT')
{{ csrf_field() }}



          <!--<div class="group-form-felid row ">
          <div class="Services">
          <label for="name" class="form-label">Category:</label>
    <select class="form-select " id="cate" name="cate"autocomplete="off">
              <option selected value disabled>-------Select Category-------</option>
                         
                         <?php
          //use Illuminate\Support\Facades\DB;
          
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
  <label for="name" class="form-label">Subcategory:</label>
  <select class="form-select" id="sctgry" name="sctgry" autocomplete="off" required="require">
                          
                        </select>
                     </div>
  </div>-->
  
 
  <div class="Servic" style="margin-top:2%;">
    <label for="name" class="form-label">News Title:</label>
    <input  type="text" id="Title" name="Title" value="{{ $post->title }}" class="form-control">
                  </div>

    <div class="Servic" style="margin-top:2%;">
    <label for="name" class="form-label">Description:</label>
<textarea id="ndescrp" name="ndescrp" style="width:880px; height:280px;">
{{ $post->description }}</textarea>

    </div>
 


         <!-- <div class="group-form-felid row mt-2">

    <div class="Services">
    <label for="name" class="form-label">Image Upload:</label>
    <input  type="file" id="image" name="image" class="form-control">
  </div>
     <div class="Services">
    <label for="name" class="form-label">News Time:</label>
    <input  type="time" id="ntime" name="ntime" class="form-control">
                  </div>

</div>--->

@endforeach
<div class="two-btn" style="margin-top: 2%;">
             <button type="submit" class="submit-btn me-4 active" name="submit" id="submit">Edit News</button>
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
    <script src="http://js.nicedit.com/nicEdit-latest.js" type="text/javascript"></script>
<script type="text/javascript">bkLib.onDomLoaded(nicEditors.allTextAreas);</script>
</body>
    </html>

    <script>
        $(document).ready(function () {
            $('#cate').on('change', function () {
                var idCountry = this.value;
                $("#sctgry").html('');
                $.ajax({
                    url: "{{url('api/fetch-submenu')}}",
                    type: "POST",
                    data: {
                        country_id: idCountry,
                        _token: '{{csrf_token()}}'
                    },
                    dataType: 'json',
                    success: function (result) {
                        $('#sctgry').html('<option value disabled selected>-------Select Subcategory-------</option><option value="Acharan">NONE</option>');
                        $.each(result.submenu, function (key, value) {
                            $("#sctgry").append('<option value="' + value
                                .id + '">' + value.name + '</option>');
                        });
                        
                    }
                });
            });
            
        });
    </script>


