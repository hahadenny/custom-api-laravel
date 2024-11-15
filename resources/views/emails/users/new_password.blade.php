@component('mail::message')

# Hello, {{ $user->first_name }}


Your new password: <strong>{{ $newPassword }}</strong>


For login use your email and new password

@component('mail::button', ['url' => config('app.front_url') . '/login'])
    Login link
@endcomponent

@endcomponent

