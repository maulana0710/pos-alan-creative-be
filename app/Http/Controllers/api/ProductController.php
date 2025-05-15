<?php

namespace App\Http\Controllers\api;

use App\Enum\HttpResponse;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\UploadFileService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use MarcinOrlowski\ResponseBuilder\ResponseBuilder;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Validator;
class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $products = Product::select(
                'id',
                'uuid',
                'name',
                'image',
                'price',
                'fl_active',
                'created_at',
                'updated_at',
            )
                ->paginate($request->per_page ?? 10);

            return ResponseBuilder::asSuccess()
                ->withData($products)
                ->build();
        } catch (\Throwable $th) {
            return ResponseBuilder::asError(500)
                ->withMessage($th->getMessage())
                ->build();
        }
    
    }
    public function allProducts(Request $request)
    {
        try {
            $products = Product::all();
            return ResponseBuilder::asSuccess()
                ->withData($products)
                ->build();
        } catch (\Throwable $th) {
            return ResponseBuilder::asError(500)
                ->withMessage($th->getMessage())
                ->build();
        }
    }
    public function allProductWithDeleted(Request $request)
    {
        try {
            $products = Product::withTrashed()->paginate($request->per_page ?? 10);

            return ResponseBuilder::asSuccess()
                ->withData($products)
                ->build();
        } catch (\Throwable $th) {
            return ResponseBuilder::asError(500)
                ->withMessage($th->getMessage())
                ->build();
        }
    }
    public function productDeleted(Request $request)
    {
        try {
            $products = Product::onlyTrashed()->paginate($request->per_page ?? 10);

            return ResponseBuilder::asSuccess()
                ->withData($products)
                ->build();
        } catch (\Throwable $th) {
            return ResponseBuilder::asError(500)
                ->withMessage($th->getMessage())
                ->build();
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $requestData = $request->validate([
                'name' => 'required|string|max:255',
                'price' => 'required|string',
                'image' => 'nullable|file|mimes:jpg,png,jpeg',
            ]);
            if ($request->hasFile('image')) {
                $fileName = UploadFileService::upload($request->image, 'product');
                $requestData['image'] = $fileName;
            }

            $data = Product::create($requestData);

            return ResponseBuilder::asSuccess()
                ->withMessage('Product created successfully.')
                ->withData($data)
                ->build();
        } catch (\Throwable $th) {
            return ResponseBuilder::asError(500)
                ->withMessage($th->getMessage())
                ->build();
        }
    }

    /**
     * Display the specified resource.
     */
    public function show($uuid)
    {
        $product = Product::where('uuid', $uuid)->first();

        if (!$product)
            throw new NotFoundHttpException('Not Found');

        return ResponseBuilder::asSuccess(HttpResponse::HTTP_CREATED)
            ->withData($product)
            ->withHttpCode(HttpResponse::HTTP_CREATED)
            ->withMessage("Show Product Successfully")
            ->build();
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $uuid)
    {

        $product = Product::where('uuid', $uuid)->firstOrFail();

        if (!$product)
            throw new NotFoundHttpException('Not Found');

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'price' => 'required|string|max:255',
            'fl_active' => 'nullable|in:1,0',
            'image' => $request->hasFile('image') ? 'file|mimes:jpg,png,jpeg|max:2048' : 'nullable',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => "Validation failed",
                'data' => $validator->errors(),
            ], 400);
        }

        $requestData = [
            'name' => $request->input('name'),
            'price' => $request->input('price'),
            'fl_active' => $request->input('fl_active', true),
        ];

        if ($request->file('image')) {
            UploadFileService::remove($product->image);
            $fileName = UploadFileService::upload($request->image, 'product');
            $requestData['image'] = $fileName;
        }

        $product->update($requestData);

        return ResponseBuilder::asSuccess(HttpResponse::HTTP_OK)
            ->withData($product)
            ->withHttpCode(HttpResponse::HTTP_OK)
            ->withMessage("Update Product Successfully")
            ->build();
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($uuid)
    {
        $product = Product::where('uuid', $uuid)->first();

        if (!$product)
            throw new NotFoundHttpException('Not Found');

        $product->delete();

        return ResponseBuilder::asSuccess(HttpResponse::HTTP_OK)
            ->withData($product)
            ->withHttpCode(HttpResponse::HTTP_OK)
            ->withMessage("Delete Product Successfully")
            ->build();
    }
    public function productRestore($uuid)
    {
        $product = Product::withTrashed()->where('uuid', $uuid)->firstOrFail();

        $product->restore();

        return ResponseBuilder::asSuccess(HttpResponse::HTTP_OK)
            ->withData($product)
            ->withHttpCode(HttpResponse::HTTP_OK)
            ->withMessage("Product Restored Successfully")
            ->build();
    }
    public function productForceDelete($uuid)
    {
        $product = Product::withTrashed()->where('uuid', $uuid)->firstOrFail();

        $product->forceDelete();

        return ResponseBuilder::asSuccess()
            ->withMessage("Product permanently deleted")
            ->build();
    }

}
