<?php

namespace App\Http\Controllers;

use App\Services\Json\JsonResponse;
use Illuminate\Http\Request;
use App\Services\App\MusicCategoryService;
use Illuminate\Validation\ValidationException;

class MusicCategoryController extends Controller
{
    public function __construct(private MusicCategoryService $musicCategoryService)
    {
    }

    # Get music categories
    public function get_music_categories()
    {
        try {
            return $this->musicCategoryService->get_music_categories();
        } catch (ValidationException $e) {
            return JsonResponse::unprocessable_entity('Unprocessable Entity', $e->errors());
        } catch (\Throwable $e) {
            \Log::error($e->getMessage(), ['exception' => $e]);
            return JsonResponse::internal_server_error('Something went wrong', null);
        }
    }

    # Create music category
    public function create_music_category(Request $request)
    {
        try {
            $create_category = $request->validate([
                "title" => "required|string|max:255"
            ]);

            return $this->musicCategoryService->create_music_category($create_category);

        } catch (ValidationException $e) {
            return JsonResponse::unprocessable_entity('Unprocessable Entity', $e->errors());
        } catch (\Throwable $e) {
            # Log the error for debugging locally
            \Log::error($e->getMessage());
            return JsonResponse::internal_server_error('Something went wrong', null);
        }
    }
}

?>
