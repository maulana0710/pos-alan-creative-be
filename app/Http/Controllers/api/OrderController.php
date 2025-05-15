<?php

namespace App\Http\Controllers\api;

use App\Enum\HttpResponse;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use MarcinOrlowski\ResponseBuilder\ResponseBuilder;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class OrderController extends Controller
{
    private function generateOrderNumber()
    {
        $year = Carbon::now()->format('y');
        $month = Carbon::now()->format('m');

        $last_order = Order::withTrashed()
            ->whereYear('created_at', Carbon::now()->year)
            ->whereMonth('created_at', Carbon::now()->month)
            ->where('orderno', 'LIKE', "ORD-{$year}{$month}%")
            ->max(DB::raw("SUBSTRING(orderno, -3)"));

        $next_order_num = $last_order ? (int) $last_order + 1 : 1;
        $order_num = str_pad($next_order_num, 3, '0', STR_PAD_LEFT);

        return "ORD-{$year}{$month}{$order_num}";
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $data = Order::select('id', 'uuid', 'orderno', 'qty', 'status', 'grand_total', 'created_at')
            ->with([
                'orderDetails.product:id,uuid,name,image,price'
            ])
            ->latest()
            ->paginate($request->per_page ?? 10);


        return ResponseBuilder::asSuccess(HttpResponse::HTTP_OK)
            ->withData($data)
            ->withHttpCode(HttpResponse::HTTP_OK)
            ->withMessage("Get Order Successfully")
            ->build();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        DB::beginTransaction();

        try {
            $totalQty = collect($request->order_package)
                ->flatMap(fn($package) => $package['products'])
                ->sum('qty');

            $orderNo = $this->generateOrderNumber();
            $orderData = [
                'total_payment' => $request->total_payment,
                'grand_total' => $request->grand_total,
                'change' => $request->change,
                'orderno' => $orderNo,
                'qty' => $totalQty
            ];

            $order = Order::create($orderData);
            foreach ($request->order_package as $package) {
                foreach ($package['products'] as $productDetail) {
                    $product = Product::where('uuid', $productDetail['uuid'])->first();
                    OrderDetail::create([
                        'product' => $product->id,
                        'orderno' => $orderNo,
                        'qty' => $productDetail['qty']
                    ]);
                }
            }

            $order->save();

            DB::commit();

            return ResponseBuilder::asSuccess(HttpResponse::HTTP_CREATED)
                ->withData($order)
                ->withHttpCode(HttpResponse::HTTP_CREATED)
                ->withMessage("Transaksi berhasil")
                ->build();
        } catch (\Exception $e) {
            DB::rollBack();
            return ResponseBuilder::asError(HttpResponse::HTTP_INTERNAL_SERVER_ERROR)
                ->withMessage("An error occurred while creating the order.")
                ->withHttpCode(HttpResponse::HTTP_INTERNAL_SERVER_ERROR)
                ->withData(['error' => $e->getMessage()])
                ->build();
        }
    }

    /**
     * Display the specified resource.
     */
    public function show($uuid)
    { {
            $data = Order::select('id', 'uuid', 'orderno', 'qty', 'status', 'grand_total', 'created_at')
                ->with([
                    'orderDetails.product:id,uuid,name,image,price'
                ])
                ->where('uuid', $uuid)
                ->latest()
                ->firstOrFail();


            return ResponseBuilder::asSuccess(HttpResponse::HTTP_OK)
                ->withData($data)
                ->withHttpCode(HttpResponse::HTTP_OK)
                ->withMessage("Get Order Successfully")
                ->build();
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Order $order)
    {
        DB::beginTransaction();

        try {
            // Hapus order detail sebelumnya
            OrderDetail::where('orderno', $order->orderno)->delete();

            // Hitung qty baru dari request
            $totalQty = collect($request->order_package)
                ->flatMap(fn($package) => $package['products'])
                ->sum('qty');

            // Update order
            $order->update([
                'total_payment' => $request->total_payment,
                'grand_total' => $request->grand_total,
                'change' => $request->change,
                'qty' => $totalQty,
            ]);

            // Buat order detail baru
            foreach ($request->order_package as $package) {
                foreach ($package['products'] as $productDetail) {
                    $product = Product::where('uuid', $productDetail['uuid'])->firstOrFail();

                    OrderDetail::create([
                        'product' => $product->id,
                        'orderno' => $order->orderno,
                        'qty' => $productDetail['qty']
                    ]);
                }
            }

            DB::commit();

            return ResponseBuilder::asSuccess(HttpResponse::HTTP_OK)
                ->withData($order->load('orderDetails.product:id,uuid,name,image,price'))
                ->withHttpCode(HttpResponse::HTTP_OK)
                ->withMessage("Order updated successfully")
                ->build();
        } catch (\Exception $e) {
            DB::rollBack();
            return ResponseBuilder::asError(HttpResponse::HTTP_INTERNAL_SERVER_ERROR)
                ->withMessage("An error occurred while updating the order.")
                ->withHttpCode(HttpResponse::HTTP_INTERNAL_SERVER_ERROR)
                ->withData(['error' => $e->getMessage()])
                ->build();
        }
    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy($orderno)
  {
    DB::beginTransaction();
    try {
      $order = Order::where('orderno', $orderno)->first();

      if (!$order)
        throw new NotFoundHttpException('Not Found');

      OrderDetail::where('orderno', $order->orderno)->delete();
      $order->forceDelete();

      DB::commit();

      return ResponseBuilder::asSuccess(HttpResponse::HTTP_OK)
        ->withHttpCode(HttpResponse::HTTP_OK)
        ->withMessage("Delete Order Successfully")
        ->build();
    } catch (\Exception $e) {
      DB::rollBack();
      return ResponseBuilder::asError(HttpResponse::HTTP_INTERNAL_SERVER_ERROR)
        ->withMessage("An error occurred while deleting the order.")
        ->withHttpCode(HttpResponse::HTTP_INTERNAL_SERVER_ERROR)
        ->withData(['error' => $e->getMessage()])
        ->build();
    }
  }

}
