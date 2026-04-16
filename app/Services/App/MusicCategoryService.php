<?php

namespace App\Services\App;

use App\Models\Category;
use App\Services\Json\JsonResponse;

class MusicCategoryService
{
    public function get_music_categories()
    {
        return Category::all();
    }

    public function create_music_category(array $create_category)
    {
        $is_category_available = Category::where("title", $create_category["title"])->exists();

        if ($is_category_available) {
            return JsonResponse::conflict($create_category["title"] . " music category already exist");
        }

        Category::create($create_category);
        return JsonResponse::created("Category created");
    }
}

?>
