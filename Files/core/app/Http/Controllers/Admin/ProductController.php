<?php

namespace App\Http\Controllers\Admin;

use App\Models\Product;
use App\Models\Category;
use App\Models\ProductImage;
use Illuminate\Http\Request;
use App\Rules\FileTypeValidate;
use App\Http\Controllers\Controller;

class ProductController extends Controller
{

    public function index()
    {
        $pageTitle = 'Products';
        $products  = Product::searchable(['name', 'category:name'])->with('category')->latest('id')->paginate(getPaginate());
        return view('admin.product.index', compact('pageTitle', 'products'));
    }

    public function create()
    {
        $pageTitle  = 'Create Products';
        $categories = Category::active()->get();
        return view('admin.product.create', compact('pageTitle', 'categories'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'                  => 'required',
            'category'              => 'required',
            'price'                 => 'required|numeric|gt:0',
            'quantity'              => 'required|integer|gt:0',
            'bv'                    => 'required|integer|gt:0',
            'description'           => 'required',
            'specification.*.name'  => 'required|sometimes',
            'specification.*.value' => 'required|sometimes',
            'gallery.*'             => ['nullable', 'image', new FileTypeValidate(['jpeg', 'jpg', 'png'])],
            'thumbnail'             => ['required', 'image', new FileTypeValidate(['jpeg', 'jpg', 'png'])],
        ], [
            'specification.*.name.required'  => "All specification name filed is required",
            'specification.*.value.required' => "All specification value filed is required",
        ]);

        $product                   = new Product();
        $product->category_id      = $request->category;
        $product->name             = $request->name;
        $product->price            = $request->price;
        $product->quantity         = $request->quantity;
        $product->description      = $request->description;
        $product->meta_title       = $request->meta_title;
        $product->meta_description = $request->meta_description;
        $product->meta_keyword     = $request->meta_keywords;
        $product->bv               = $request->bv;

        if ($request->hasFile('thumbnail')) {
            try {
                $thumb              = getThumbSize('products');
                $product->thumbnail = fileUploader($request->thumbnail, getFilePath('products'), getFileSize('products'), null, $thumb);
            } catch (\Exception $exp) {
                $notify[] = ['error', 'Couldn\'t upload language image'];
                return back()->withNotify($notify);
            }
        }

        if ($request->specification) {
            $product->specifications = array_values($request->specification);
        }
        $product->save();

        $image = $this->insertImages($request, $product);
        if (!$image) {
            return response()->json([
                'status'  => 'error',
                'message' => "Couldn\'t upload product gallery images",

            ]);
        }

        $notify[] = ['success', 'Product has been saved successfully'];
        return back()->withNotify($notify);
    }

    public function edit($id)
    {
        $product    = Product::with('category', 'images')->findOrFail($id);
        $pageTitle  = 'Edit Product:' . $product->name;
        $categories = Category::active()->get();

        $images = [];


        foreach ((@$product->images ??  []) as $key => $image) {
            $img['id']  = $image->id;
            $img['src'] = getImage(getFilePath('products') . '/' . $image->name);
            $images[]   = $img;
        }
        return view('admin.product.edit', compact('pageTitle', 'categories', 'product', 'images'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name'                  => 'required',
            'category'              => 'required',
            'price'                 => 'required|numeric|gt:0',
            'quantity'              => 'required|integer|gt:0',
            'bv'                    => 'required|integer|gt:0',
            'description'           => 'required',
            'specification.*.name'  => 'required|sometimes',
            'specification.*.value' => 'required|sometimes',
            'galleryImages.*'       => ['nullable', 'image', new FileTypeValidate(['jpg', 'jpeg', 'png'])],
            'thumbnail'             => ['nullable', 'image', new FileTypeValidate(['jpg', 'jpeg', 'png'])],

        ], [

            'specification.*.name.required'  => "All specification name filed is required",
            'specification.*.value.required' => "All specification value filed is required",
        ]);

        $product                   = Product::findOrFail($id);
        $product->name             = $request->name;
        $product->category_id      = $request->category;
        $product->price            = $request->price;
        $product->quantity         = $request->quantity;
        $product->description      = $request->description;
        $product->meta_title       = $request->meta_title;
        $product->meta_description = $request->meta_description;
        $product->bv               = $request->bv;
        $product->meta_keyword     = $request->meta_keywords;

        if ($request->specification) {
            $product->specifications = array_values($request->specification);
        } else {
            $product->specifications = null;
        }
        if ($request->hasFile('thumbnail')) {
            try {
                $thumb              = getThumbSize('products');
                $product->thumbnail = fileUploader($request->thumbnail, getFilePath('products'), getFileSize('products'), null, $thumb);
            } catch (\Exception $exp) {
                $notify[] = ['error', 'Couldn\'t upload language image'];
                return back()->withNotify($notify);
            }
        }

        $image = $this->insertImages($request, $product, $id);
        if (!$image) {
            $notify[] = ['error', 'Couldn\'t upload product gallery images'];
            return back()->withNotify($notify);
        }

        $product->save();

        $notify[] = ['success', 'Product has been updated successfully'];
        return back()->withNotify($notify);
    }

    protected function insertImages($request, $product, $id = null)
    {
        $path = getFilePath('products');

        if ($id) {
            $this->removeImages($request, $product, $path);
        }

        $hasImages = $request->file('gallery');

        if ($request->hasFile('gallery')) {
            $size      = getFileSize('products');
            $thumbSize = getThumbSize('products');
            $images    = [];

            foreach ($hasImages as $file) {
                try {
                    $name              = fileUploader($file, $path, $size, null, $thumbSize);
                    $image             = new ProductImage();
                    $image->product_id = $product->id;
                    $image->name       = $name;
                    $images[]          = $image;
                } catch (\Exception $exp) {
                    return false;
                }
            }
            $product->images()->saveMany($images);
        }
        return true;
    }

    protected function removeImages($request, $product, $path)
    {
        $previousImages = $product->images->pluck('id')->toArray();
        $imageToRemove  = array_values(array_diff($previousImages, $request->old ?? []));
        foreach ($imageToRemove as $item) {
            $productImage = ProductImage::find($item);
            fileManager()->removeFile($path . '/' . $productImage->name);
            fileManager()->removeFile($path . '/thumb_' . $productImage->name);
            $productImage->delete();
        }
    }

    public function status($id)
    {
        return Product::changeStatus($id);
    }

    public function feature($id)
    {
        return Product::changeStatus($id, 'is_featured');
    }
}
