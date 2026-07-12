@if (Route::has('login'))
   
        @auth
            <a href="{{ url('/home') }}" class="text-sm text-gray-700 dark:text-gray-500 underline">Home</a>
            <a href="{{ route('emplogout') }}" class="text-sm text-gray-700 dark:text-gray-500 underline">Logout</a>
        @else
           <!-- <a href="{{ route('login') }}" class="text-sm text-gray-700 dark:text-gray-500 underline">Log in</a>

            @if (Route::has('register'))
                <a href="{{ route('register') }}" class="ml-4 text-sm text-gray-700 dark:text-gray-500 underline">Register</a>
            @endif-->
        @endauth
    </div>
@endif