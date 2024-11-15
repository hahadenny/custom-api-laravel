<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\Company;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;

class CompanySocketUpdateCommand extends Command
{
    protected $signature = 'company-socket:update {socket_url?} {email?}';

    protected $description = 'Command description';

    public function handle() : int
    {
        $email = $this->argument('email') ?? $this->ask('Login Email?');
        $password = $this->secret('Login Password ?');

        $attempt = Auth::attempt(['email' => $email, 'password' => $password]);
        if(!$attempt) {
            $this->error('User login failed.');
            return Command::INVALID;
        }

        $user = auth()->user();

        $companyName = 'Main Company';
        $company = Company::where('name', 'like', $companyName)->first();
        $company = Company::first();

        if(isset($company) && ($user->role === UserRole::SuperAdmin
            || ($user->role == UserRole::Admin && $user->company_id === $company->id ))){
            // logged-in user is super admin or company admin
            $socket_url = $this->promptForSocketUrl();
            if($company->update(['ue_url'=>$socket_url])){
                $this->info('"'.$company->name.'" socket URL was successfully set to "'.$socket_url.'"');
                return Command::SUCCESS;
            }
            $this->error("Company socket server URL update failed.");
            return Command::FAILURE;
        }

        $this->error('User "'.$user->email.'" is not authorized for "'.$company->name.'"');
        return Command::INVALID;
    }

    protected function promptForSocketUrl(string $message="", array $data=[]) : string
    {
        $socket_url = $this->argument('socket_url') ?? $this->ask('New Socket Server URL?');
        if(config('app.onprem')){
            // make sure scheme is `http` and `port` was specified
            $socket_pieces = parse_url($socket_url);
            if($socket_pieces['scheme'] !== 'http'){
                // TODO: socket should be 'http' only
            }
            if(empty($socket_pieces['port'])){
                $socket_pieces['port'] = $this->ask('Please enter Socket Server URL port (leave blank for default of 6001): ', 6001);
            }
            $socket_url = $this->unparseUrl($socket_pieces);
        }
        return $socket_url;
    }

    private function unparseUrl($parsedUrl) : string
    {
        $scheme   = isset($parsedUrl['scheme']) ? $parsedUrl['scheme'] . '://' : '';
        $host     = $parsedUrl['host'] ?? '';
        $port     = isset($parsedUrl['port']) ? ':' . $parsedUrl['port'] : '';
        $user     = $parsedUrl['user'] ?? '';
        $pass     = isset($parsedUrl['pass']) ? ':' . $parsedUrl['pass']  : '';
        $pass     = ($user || $pass) ? "$pass@" : '';
        $path     = $parsedUrl['path'] ?? '';
        $query    = isset($parsedUrl['query']) ? '?' . $parsedUrl['query'] : '';
        $fragment = isset($parsedUrl['fragment']) ? '#' . $parsedUrl['fragment'] : '';

        return "$scheme$user$pass$host$port$path$query$fragment";

    }
}
