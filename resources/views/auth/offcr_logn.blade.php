

<html lang="en">

<head>
    <title>News</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!--------css------------->

    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.7.0/css/all.css" integrity="sha384-lZN37f5QGtY3VHgisS14W3ExzMWZxybE1SJSEsQp9S+oqd12jhcu+A56Ebc1zFSJ" crossorigin="anonymous">

    <link href="https://demo.dashboardpack.com/architectui-html-free/main.css" rel="stylesheet">

    <!----bootstrap----->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!---costum-css---->
    <link href="{{ asset('css/form.css') }}" rel="stylesheet">
   
 <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
</head>

<body>

    @extends('Employe.layouts.app')
@section('content')
    <div class="app-container app-theme-white body-tabs-shadow fixed-sidebar fixed-header">

    
        <!------log-in form  start--------->
        <div class="container-fluid log-in_form">
            <div class="row">
                <div class="col-lg-4 offset-lg-4 col-md-6 offset-md-3 col-sm-8 offset-sm-2"  style="background-color: #3f3e64; border-radius: 18%;">
                    <div class="group-form">


                        <h1 class="text-center" style="font-weight: 700;">Admin Log In</h1>

 @if (Session::has('error'))
                            <p class="text-danger">{{ Session::get('error') }}</p>
                        @endif
                        @if (Session::has('success'))
                            <p class="text-success">{{ Session::get('success') }}</p>
                        @endif


<form action="{{ route('emploginn') }}" method="POST">
                            @csrf
                            @method('post')

                <div class="email my-3">
    
                                <input type="email" name="email" class="form-control" placeholder="Email" />
                                @if ($errors->has('email'))
                                    <p class="text-danger">{{ $errors->first('email') }}</p>
                                @endif
                            </div>

    <div class="pass my-3">
 
        <input type="password" name="password" class="form-control" placeholder="Password" />
        @if ($errors->has('password'))
            <p class="text-danger">{{ $errors->first('password') }}</p>
        @endif
    </div>

        <div class="two-btn mt-3"  align="center">
            <input type="submit" style="font-weight: 700; color: #24223a;" class="submit-btn me-4" value=" Login " />
            <button class="submit-btn "  style="font-weight: 700;"> <a href="{{ url('/indeex') }}" style="color: #24223a;">Dashboard</a></button>
    </div>
            <div class="form-check my-2">
                                <hr>
            </div>
        
               
    </form>

        </div>
    </div>
            </div>
        </div>
        <!------log-in form end--->


    </div>
@endsection
 <script src="https://kit.fontawesome.com/201ed92cd4.js" crossorigin="anonymous"></script>
<script type="text/javascript" src="https://demo.dashboardpack.com/architectui-html-free/assets/scripts/main.js"></script>
</body>

</html>
