<?php

namespace App\Services;

use App\Models\Category;

class CategoryService {

    public function getAllCategories($sortBy = 'name'){
        return Category::orderBy($sortBy, 'asc')->get();
    }
}
