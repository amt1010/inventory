<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AddemployeeController;
use App\Http\Controllers\Auth\Login\EmployeeController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SubmenuController;
use App\Http\Controllers\EmploginController;
use App\Http\Controllers\DropdownController;
use App\Http\Controllers\AddnewsController;
use App\Http\Controllers\TopmenuController;
use App\Http\Controllers\LastmenuController;
use App\Http\Controllers\EditnewsController;
use App\Http\Controllers\ViewempController;

/* 
|--------------------------------------------------------------------------
| Web Routes 
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::get('/user', function () {
    return view('userlogin');
});

Route::get('/clear', function() {

    Artisan::call('cache:clear');
    Artisan::call('config:clear');
    Artisan::call('config:cache');
    Artisan::call('view:clear');
    Artisan::call('route:clear');

    return "Cleared!";

});

Route::group(['middleware'=>'guest'],function(){
    Route::get('login',[AuthController::class,'index'])->name('login');
    Route::post('login',[AuthController::class,'login'])->name('login')->middleware('throttle:2,1');

    Route::get('register',[AuthController::class,'register_view'])->name('register');
    Route::post('register',[AuthController::class,'register'])->name('register')->middleware('throttle:2,1');
});



Route::group(['middleware'=>'auth'],function(){
    Route::get('home',[AuthController::class,'home'])->name('home');
    Route::get('logout',[AuthController::class,'logout'])->name('logout');
});


Route::view('newsemp', 'Employe.employee');
Route::view('addcategory', 'Employe.Addmenu');
Route::view('categorylist', 'Employe.Category_list');
Route::view('subcategorylist', 'Employe.subcategory_list');
Route::view('addsubcategory', 'Employe.Addsubmenu');
Route::view('adduser', 'Employe.Add_user');
Route::view('userlist', 'Employe.User_list');
Route::view('addProduct', 'Employe.Add_product');
Route::view('useraddProduct', 'Add_product');
Route::view('addthrdcategory', 'Employe.third_category');
Route::view('thrdcategorylist', 'Employe.third_category_list');
Route::view('Productlist', 'Employe.Productlist');
Route::view('userProductlist', 'Productlist');
Route::view('admnquery', 'Admin_query');
Route::view('viewadmnquery', 'Viewadmin_query');
Route::view('viewuserquery', 'Employe.Viewuser_query');


Route::view('emplogin', 'welcome');
//Route::view('indeex', 'welcome');
Route::view('addnews', 'News_add');
Route::view('viewnews', 'News_view');
Route::view('empDash', 'emp_dash');
Route::view('tmenu', 'Addtopmenu');
Route::view('lmenu', 'Addlastmenu');
Route::view('vwemp', 'Employe.viewemploye');
Route::view('vwsbcr', 'Employe.viewsuscriber');
Route::view('check', 'Employe.abc');


Route::post('Add-EMP', [AddemployeeController::class, 'addemp']);
Route::post('Add-news', [AddnewsController::class, 'addnews']);

Route::post('Add-Menu', [ProductController::class, 'addmenu']);
Route::delete('/{product}', [ProductController::class, 'destroy'])->name('destroy');
Route::get('/category/{id}/edit', [ProductController::class, 'uupdate'])->name('category.update');
Route::post('/category/{id}', [ProductController::class, 'eedit'])->name('category.edit');

Route::post('Add-Smenu', [SubmenuController::class, 'addsmenu']);
Route::delete('smenudel/{id}', [SubmenuController::class, 'delete'])->name('submenu.delete');
Route::get('/color/{id}/edit', [SubmenuController::class, 'update'])->name('color.update');
Route::get('/colorv/{id}/edit', [SubmenuController::class, 'updatev']);
Route::post('/color/{id}', [SubmenuController::class, 'edit'])->name('color.edit');

Route::post('api/fetch-submenu', [DropdownController::class, 'fetchState']);
Route::post('api/fetch-thrmenu', [DropdownController::class, 'fetchStatess']);

//Route::get('emplogin',[EmployeeController::class,'showLoginForm'])->name('emplogin');

Route::post('elogin', [EmploginController::class, 'login'])->name('emploginn')->middleware('throttle:2,1');
Route::get('emphome',[EmploginController::class,'home'])->name('emphome');
Route::get('emplogout',[EmploginController::class,'emplogout'])->name('emplogout');

Route::post('Add-tMenu', [TopmenuController::class, 'addtmenu']);
Route::delete('topmenudel/{tid}', [TopmenuController::class, 'tmdelete'])->name('topmenu.delete');
Route::get('/tmenu/{id}/edit', [TopmenuController::class, 'tuupdate'])->name('tmenu.update');
Route::post('/tmenu/{id}', [TopmenuController::class, 'teedit'])->name('tmenu.edit');
Route::post('Add-Thrmenu', [SubmenuController::class, 'thrdsmenu']);

Route::post('Add-lstMenu', [LastmenuController::class, 'addlmenu']);
Route::delete('lastmenudel/{lid}', [LastmenuController::class, 'lmdelete'])->name('Lastmenu.delete');
Route::get('/lmenu/{id}/edit', [LastmenuController::class, 'luupdate'])->name('lmenu.update');
Route::post('/lmenu/{id}', [LastmenuController::class, 'leedit'])->name('lmenu.edit');

Route::get('get-id/{id}', [EditnewsController::class, 'Editnews']);
Route::put('update-student/{id}', [EditnewsController::class, 'update']);
Route::put('updtstatus/{id}', [EditnewsController::class, 'updateStatus']);
Route::put('updtproduct/{id}', [AddemployeeController::class, 'updateproduct']);

Route::get('view-emp/{id}', [ViewempController::class, 'Viewempdtl']);
Route::delete('employdel/{id}', [ViewempController::class, 'empdestroy'])->name('emp.delete');

Route::post('vscategory', [ProductController::class, 'eedit']);
Route::post('vssubcategory', [SubmenuController::class, 'edit']);
Route::post('vssubcategoryv', [SubmenuController::class, 'editv']);
Route::post('deletedsubcat', [SubmenuController::class, 'delete']);
Route::post('deletedsubcatv', [SubmenuController::class, 'deletev']);
Route::post('deleteuser', [EditnewsController::class, 'delete']);

Route::post('Add-product', [AddemployeeController::class, 'inputArrayToString']);
Route::get('display/{id}', [AddemployeeController::class, 'fetchdetail']);
Route::get('editproduct/{id}', [AddemployeeController::class, 'editproduct']);
Route::post('deleteproduct', [AddemployeeController::class, 'delete']);

Route::post('Add-query', [ProductController::class, 'admnnquery']);
Route::put('updtquery/{id}', [AddemployeeController::class, 'updtuquery']);
Route::post('deletequery', [AddemployeeController::class, 'deleteuquery']);

