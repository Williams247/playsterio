<?php

namespace App\Http\Controllers;

use App\Services\Json\JsonResponse;

class HealthController extends Controller
{
    public function ping()
    {
       return JsonResponse::success("App is up and running");
    }
}

?>
