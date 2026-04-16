<?php

namespace App\Services\App;

use App\Mail\SendOtpMail;
use Illuminate\Support\Facades\Mail;

class SendMailService
{
    public function sendOtpMail(string $email, string $otp, $subject): void
    {
        Mail::to($email)->send(new SendOtpMail($otp, $subject));
    }
}

?>
