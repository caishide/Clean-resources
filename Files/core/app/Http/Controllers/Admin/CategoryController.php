<?php

namespace App\Http\Controllers\Admin;

use App\Models\Category;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class CategoryController extends Controller
{
    public function index()
    {
        $pageTitle  = "Categories";
        $categories = Category::searchable(['name'])->orderBy('name')->paginate(getPaginate());
        return view('admin.category.index', compact('pageTitle', 'categories'));
    }


    public function store(Request $request, $id = 0)
    {
        $request->validate([
            'name' => 'required',
        ]);

        if (!$id) {
            $notification = 'Category added successfully';
            $category     = new Category();
        } else {
            $notification = 'Category updated successfully';
            $category     = Category::findOrFail($id);
        }

        $category->name = $request->name;
        $category->save();

        $notify[] = ['success', $notification];
        return back()->withNotify($notify);
    }

    public function status($id)
    {
        return Category::changeStatus($id);
    }

}
