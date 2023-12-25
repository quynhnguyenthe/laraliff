# Laraliff

## Overview

- [LIFFアプリ](https://developers.line.biz/en/docs/liff/overview/) Document LIFF
- [tymondesigns/jwt-auth](https://github.com/tymondesigns/jwt-auth) Repo jwt

## What you can do with laraliff

1. LIFF's [ID token] (https://developers.line.biz/ja/docs/liff/using-user-profile/#%E3%83%A6%E3%83%BC%E3%82%B5%E3%82%99%E3%83%BC%E6%83%85%E5%A0%B1%E3%82%92%E3%82%B5%E3%83%BC%E3%83%8F%E3%82%99%E3%83%BC%E3%81%A6%E3%82%99%E4%BD%BF%E7%94%A8%E3%81%99%E3%82%8B)Using user data in LIFF apps and servers
2. Once authenticated, the transition is to authenticate using JWT.

## How to use
```sh
composer require quynhnguyenthe/laraliff
```
#### [tymondesigns/jwt-auth](https://github.com/tymondesigns/jwt-auth) Create jwt config file
```sh
php artisan vendor:publish --provider="Tymon\JWTAuth\Providers\LaravelServiceProvider"
```

#### Create laraliff config file

```sh
php artisan vendor:publish --provider="QuynhNguyenThe\Laraliff\Providers\LaraliffServiceProvider"
```

#### JWT secret key generate

```sh
php artisan jwt:secret
```

#### ADD LIFF_CHANNEL_ID to .env

```
...
LIFF_CHANNEL_ID=xxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
```

#### Add the following to the schema of the table used for authentication
- `liff_id`
  - LIFF ID
- `name`
  - Name in line app
```php:create_user.php
<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('liff_id')->unique();
            $table->string('name');
            $table->timestamps();
        });
    }
    ...
}

```

##### ※Schema name can be changed from config

```php:laraliff.php
<?php

return [
    'liff_channel_id' => env('LIFF_CHANNEL_ID', 'liff_channel_id'),
    'fields' => [
        'liff_id' => 'liff_id',
        'name' => 'name',
    ],
];

```

#### Add the following method to the model used for authentication

```php:User.php
namespace App;

use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable implements JWTSubject
{
    use Notifiable;

    // Rest omitted for brevity

    /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     *
     * @return mixed
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     *
     * @return array
     */
    public function getJWTCustomClaims()
    {
        return [];
    }
}
```

#### Change `config/auth.php`

```php:auth.php
'defaults' => [
    'guard' => 'api',
    'passwords' => 'users',
],

...

'guards' => [
    'api' => [
        'driver' => 'laraliff',
        'provider' => 'users',
    ],
],
```

#### Add route for authentication

```php:route.php
Route::group([

    'middleware' => 'api',
    'prefix' => 'auth'

], function ($router) {

    Route::post('login', 'AuthController@login');
    Route::post('logout', 'AuthController@logout');
    Route::post('refresh', 'AuthController@refresh');
    Route::post('me', 'AuthController@me');

});
```

#### Create a controller for authentication
```php:Auth.php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\User;
use QuynhNguyenThe\Laraliff\Services\Exceptions\LiffUnverfiedException;
use QuynhNguyenThe\Laraliff\Services\LiffVerificationService;

class AuthController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api', ['except' => ['login']]);
    }

    public function register(LiffVerificationService $verificationService)
    {
        try {
            $liff = $verificationService->verify(request('token'));
        } catch (LiffUnverfiedException $e) {
            return response()->json(['error' => 'LIFF ID Token is unauthorized'], 401);
        }

        $user = User::create([
            'liff_id' => $liff['sub'],
            'name' => $liff['name'],
            'picture' => $liff['picture'],
        ]);

        return response()->json(auth('api')->login($user));
    }

    public function login()
    {
        try {
            $jwt = auth('api')->attempt(request(['liff_id_token']));
        } catch (LiffUnverfiedException $e) {
            return response()->json(['error' => 'LIFF ID Token is unauthorized'], 401);
        }
        if (!$jwt) {
            return response()->json(['error' => 'User not found'], 404);
        }

        return response()->json($jwt);
    }

    public function me()
    {
        return response()->json(auth('api')->user());
    }

    public function logout()
    {
        auth()->logout();

        return response()->json(['message' => 'Successfully logged out']);
    }
}
```
