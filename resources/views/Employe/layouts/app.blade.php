
<!DOCTYPE html>
<html dir="ltr" lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <!-- Tell the browser to be responsive to screen width -->
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>News</title>
    <!-- This page css -->
    <!-- Custom CSS -->
   <link rel="stylesheet" href="https://unpkg.com/@tabler/icons@latest/iconfont/tabler-icons.min.css">
   <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
   <link href="{{ asset('css/style.css') }}" rel="stylesheet"> 
  
</head>
<body>

  @yield('content')

</body>

</html>
 

 <script>
  $(document).keydown(function(e){
    if(e.which === 123){
       return false;
    }
});


  $(document).bind("contextmenu",function(e) {
 e.preventDefault();
});

</script>
