<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Request;
use App\Http\Controllers\Api\AuthController;

class TestOtpSending extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:test-otp-sending {email : The email address to send OTP to}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test sending OTP email to a specific address';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = $this->argument('email');
        $this->info("Testing OTP sending to: {$email}");

        // Create a mock request
        $request = Request::create('/api/send-otp', 'POST', ['email' => $email]);
        
        // Call the AuthController's sendOtp method
        $controller = new AuthController();
        $response = $controller->sendOtp($request);
        
        $this->info("Response status: " . $response->status());
        $this->line("Response content: " . $response->getContent());
    }
}
