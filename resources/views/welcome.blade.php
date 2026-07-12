<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0">
<meta name="description" content="POS - Bootstrap Admin Template">
<meta name="keywords" content="admin, estimates, bootstrap, business, corporate, creative, invoice, html5, responsive, Projects">
<meta name="author" content="Dreamguys - Bootstrap Admin Template">
<meta name="robots" content="noindex, nofollow">
<title>Inventory Solutions</title>

<link rel="shortcut icon" type="image/x-icon" href="img/favicon.png">

<link rel="stylesheet" href="{{ asset('css/bootstrap.min.css') }}">

<link rel="stylesheet" href="{{ asset('plugins/fontawesome/css/fontawesome.min.css') }}">
<link rel="stylesheet" href="{{ asset('plugins/fontawesome/css/all.min.css') }}">

<link rel="stylesheet" href="{{ asset('css/style.css') }}">
</head>
<body class="account-page">
   
<div class="main-wrapper">
<div class="account-content">
<div class="login-wrapper">
<div class="login-content">
<div class="login-userset">
<div class="login-logo">
<img src="img/logo.png" alt="img">
</div>
<div class="login-userheading">
<h3>Admin Sign In</h3>
<h4>Please login to your account</h4>
</div>


 @if (Session::has('error'))
                            <p class="text-danger">{{ Session::get('error') }}</p>
                        @endif
                        @if (Session::has('success'))
                            <p class="text-success">{{ Session::get('success') }}</p>
                        @endif


<form action="{{ route('emploginn') }}" method="POST">
                            @csrf
                            @method('post')

<div class="form-login">
<label>Email</label>
<div class="form-addons">
<input type="email" name="email" placeholder="Enter your email address" autocomplete="off">
 @if ($errors->has('email'))
             <p class="text-danger">{{ $errors->first('email') }}</p>
  @endif
<img src="img/icons/mail.svg" alt="img">
</div>
</div>


<div class="form-login">
<label>Password</label>
<div class="pass-group">
<input type="password" name="password" class="pass-input" placeholder="Enter your password">
 @if ($errors->has('password'))
            <p class="text-danger">{{ $errors->first('password') }}</p>
        @endif
<span class="fas toggle-password fa-eye-slash"></span>
</div>
</div>

<div class="form-login">
<div class="alreadyuser">
<h4><a href="forgetpassword.html" class="hover-a">Forgot Password?</a></h4>
</div>
</div>


<div class="form-login">

<input type="submit"  class="btn btn-login" value="Sign In">
</div>

</form>

</div>
</div>
<div class="login-img">
<img src="img/login.jpg" alt="img">
</div>
</div>
</div>
</div>


<script src="js/jquery-3.6.0.min.js"></script>

<script src="js/feather.min.js"></script>

<script src="js/bootstrap.bundle.min.js"></script>

<script src="js/script.js"></script>
</body>
</html>