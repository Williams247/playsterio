<?php
namespace App\Services\App;

use App\Models\User;
use App\Models\Otp;
use Illuminate\Support\Facades\Auth;
use App\Services\Json\JsonResponse;
use Illuminate\Http\Request;
use App\Services\App\SendMailService;

class AuthService
{
    public function __construct(private SendMailService $sendMailService)
    {
    }
    public function register_user(array $data)
    {

        if (User::exists()) {
            return JsonResponse::forbidden("Operation failed", null);
        }

        if (User::where("email", $data["email"])->exists()) {
            return JsonResponse::forbidden("Operation failed", null);
        }

        User::create([
            "name" => $data["name"],
            "email" => $data["email"],
            "password" => $data["password"],
        ]);

        return JsonResponse::created("Operation successful", null);
    }

    public function login_user(Request $request)
    {
        if (!Auth::attempt($request->only('email', 'password'))) {
            return JsonResponse::not_found("Record not found", null);
        }

        $user = Auth::user();
        $token = $user->createToken('token')->plainTextToken;

        return JsonResponse::success('Login successful', [
            'token' => $token,
            'token_type' => 'Bearer',
        ]);
    }

    public function forgot_password(array $data)
    {

        if (!User::where("email", $data["email"])->exists()) {
            return JsonResponse::not_found("Operation failed", null);
        }

        $otp = (string) random_int(100000, 999999);

        Otp::create([
            "email" => $data["email"],
            "otp_code" => $otp,
            "otp_type" => "otp"
        ]);

        $this->sendMailService->sendOtpMail($data['email'], $otp, 'Forgot Password');
        return JsonResponse::success('An OTP has been sent to your email, please check in 30sec', null);
    }

    public function reset_password(array $data)
    {
        if (!User::where("email", $data["email"])->exists()) {
            return JsonResponse::not_found("Operation failed", null);
        }

        $user_otp = Otp::where("email", $data["email"])
            ->where("otp_code", $data["otp_code"])
            ->where("otp_type", "otp")
            ->first();

        if (!$user_otp) {
            return JsonResponse::not_found("Operation failed", null);
        }

        if ($user_otp->created_at->addMinutes(15)->isPast()) {
            $user_otp->delete();
            return JsonResponse::not_found("Otp expired or Invalid", null);
        }

        $user = User::where("email", $data["email"])->first();
        $user->update([
            "password" => $data["password"],
        ]);

       $user_otp->delete();

       return JsonResponse::success("Password updated", null);

    }

    public function logout_user(Request $request)
    {
        $token = $request->user()?->currentAccessToken();

        if ($token && method_exists($token, 'delete')) {
            $token->delete();
        }

        return JsonResponse::success('Logged out', null);
    }
}

?>
