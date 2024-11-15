<?php
/*
 * Generate Tariff
 * @author: Denny CHoi
 */
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Company;

class SaveMedia extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'SaveMedia {user_id} {media_id} {url}';
	
	public $user_id = '';
    
    public $media_id = '';
    
    public $url = '';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Save Media';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $user_id = $this->user_id = $this->argument('user_id');        
        $media_id = $this->media_id = $this->argument('media_id');        
        $url = $this->url = $this->argument('url');     

        $user = User::find($user_id);       
        
        $rs = $user->company->addMediaFromUrl($url)->toMediaCollection(Company::MEDIA_COLLECTION_NAME); //comment out for testing
        $user->company->media()->find($rs->id)->update(['h264_preview' => $media_id]); //comment out for testing
        
        $data = array(
            'media_id' => $media_id
            );
        $app_tools_url = env('APP_TOOLS_URL', 'https://tools.porta.solutions');
        $result = $this->d3_curl($app_tools_url.'/api/removeH264', 'POST', $data);
    }
    
    public function d3_curl($url, $method = 'GET', $post_data = array(), $headers = array()) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        // SSL important
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        $timeout = 10;
        if ($method === 'POST')
            $timeout = 99999;
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout); //timeout in seconds

        $output = curl_exec($ch);        
        if (curl_errno($ch)) {
            $error_msg = curl_error($ch);
            echo $error_msg; exit;
        }
        curl_close($ch);
        
        return $output;        
    }
}