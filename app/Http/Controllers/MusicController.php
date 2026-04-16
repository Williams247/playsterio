<?php

namespace App\Http\Controllers;

use App\Services\App\MusicService;
use App\Services\Json\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class MusicController extends Controller
{
    public function __construct(private MusicService $musicService)
    {
    }

    # Get music
    public function get_music(Request $request)
    {
        try {
            return $this->musicService->get_music($request->query('category'));
        } catch (ValidationException $e) {
            return JsonResponse::unprocessable_entity('Unprocessable Entity', $e->errors());
        } catch (\Throwable $e) {
            \Log::error($e->getMessage(), ['exception' => $e]);
            return JsonResponse::internal_server_error('Something went wrong', null);
        }
    }

    # Initiate music save operation
    public function init_create_music()
    {
        try {
            return $this->musicService->init_create_music();
        } catch (\Throwable $e) {
            \Log::error($e->getMessage(), ['exception' => $e]);
            return JsonResponse::internal_server_error('Something went wrong', null);
        }
    }

    # Save music
    public function save_music(Request $request)
    {
        if (env('MUSIC_KP') !== $request->headers->get('kp')) {
            return JsonResponse::forbidden("You're forbidden here", null);
        }

        try {
            $create_music = $request->validate([
                'title' => 'required|string|max:255',
                'filename' => 'required|string|max:255',
                'music_url' => 'required|url',
                'thumbnail_url' => 'required|url',
                'duration' => 'required|string|max:64',
                'description' => 'required|string|max:10000',
                'category' => 'required|string|max:255',
            ]);

            return $this->musicService->save_music($create_music);
        } catch (ValidationException $e) {
            return JsonResponse::unprocessable_entity('Unprocessable Entity', $e->errors());
        } catch (\Throwable $e) {
            \Log::error($e->getMessage(), ['exception' => $e]);
            return JsonResponse::internal_server_error('Something went wrong', null);
        }
    }

    public function edit_music_category(Request $request)
    {
        try {
            $update_music_category = $request->validate([
                "category" => "required|string|max:255",
                "new_category" => "required|string|max:255"
            ]);

            return $this->musicService->edit_music_category([
                "id" => $request->query("id"),
                "category" => $update_music_category["category"],
                "new_category" => $update_music_category["new_category"]
            ]);

        } catch (ValidationException $e) {
            return JsonResponse::unprocessable_entity('Unprocessable Entity', $e->errors());
        } catch (\Throwable $e) {
            \Log::error($e->getMessage(), ['exception' => $e]);
            return JsonResponse::internal_server_error('Something went wrong', null);
        }
    }

    public function enable_disable_music(Request $request)
    {
        try {
            $edm = $request->validate([
                "disabled" => "required|boolean",
            ]);

            return $this->musicService->enable_disable_music([
                "id" => $request->query("id"),
                "disabled" => $edm["disabled"]
            ]);

        } catch (ValidationException $e) {
            return JsonResponse::unprocessable_entity('Unprocessable Entity', $e->errors());
        } catch (\Throwable $e) {
            \Log::error($e->getMessage(), ['exception' => $e]);
            return JsonResponse::internal_server_error('Something went wrong', null);
        }
    }
}

?>
