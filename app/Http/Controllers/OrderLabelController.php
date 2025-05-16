<?php

namespace App\Http\Controllers;

use App\Models\Order;
use DB;
use Illuminate\Http\Request;
use MarcinOrlowski\ResponseBuilder\ResponseBuilder;
use TCPDF;

class OrderLabelController extends Controller
{
    public function printLabel($orderno)
    {
        try {
            $order = Order::with('orderDetails.product')
                ->where('orderno', $orderno)
                ->first();

            if (!$order) {
                return ResponseBuilder::asError(404)->withMessage('Order not found')->build();
            }

            $pdf = new TCPDF();
            $pdf->setTitle('Order Delivery Label');
            $pdf->setMargins(1, 1, 1);
            $pdf->setFont('helvetica', '', 8);
            $pdf->setAutoPageBreak(FALSE, 1);

            $complex_cell_border = [
                'B' => ['width' => 0.2, 'color' => [0, 0, 0], 'dash' => 0, 'cap' => 'square'],
            ];

            $pdf->AddPage('P', [100, 78]);

            $pdf->Image(public_path('img/logo.png'), '', 2, 10, 10, '', '', '', false, 300, 'C', false, false, 0, false, false, false);
            $pdf->Cell(75, 3, '1', 0, false, 'R', 0, '', 0, false, 'T', 'M');
            $pdf->setY(13);

            $pdf->SetFont('helvetica', 'B', 6);
            $pdf->Cell(76, 1, '', $complex_cell_border, '', 'C');
            $pdf->ln();

            $pdf->SetFont('helvetica', 'B', 7);
            $pdf->Cell(76, 3, $order->orderno . ' / ' . date('d-m-Y', strtotime($order->created_at)), $complex_cell_border, '', 'C');
            $pdf->ln();

            $pdf->SetFont('helvetica', 'B', 7);
            $pdf->Cell(15, 3, 'PESANAN : ', 0, 1, 'L');

            $pdf->SetFont('helvetica', 'B', 10);

            $products = DB::table('order_details as od')
                ->join('products as p', 'od.product', '=', 'p.id')
                ->where('od.orderno', $order->orderno)
                ->select('p.name as product_name', 'od.qty')
                ->get();

            foreach ($products as $product) {
                $pdf->Cell(70, 4, '• ' . $product->product_name . ' x ' . $product->qty, 0, 1, 'L');
            }

            $pdf->ln(1);
            $pdf->SetFont('helvetica', 'B', 6);
            $pdf->Cell(76, 1, '', $complex_cell_border, '', 'C');
            $pdf->ln();

            $exportPath = storage_path('app/public/export');
            if (!file_exists($exportPath)) {
                mkdir($exportPath, 0755, true);
            }

            $filename = preg_replace('/[^A-Za-z0-9_\-]/', '_', $orderno) . '.pdf';
            $filePath = $exportPath . '/' . $filename;

            $pdf->Output($filePath, 'F');

            if (!file_exists($filePath)) {
                throw new \Exception("Gagal menyimpan file PDF ke: $filePath");
            }

            return ResponseBuilder::asSuccess()
                ->withMessage('Label printed successfully')
                ->withData(['file_path' => asset('storage/export/' . $orderno . '.pdf')])
                ->build();
        } catch (\Throwable $th) {
            return ResponseBuilder::asError(500)
                ->withMessage($th->getMessage())
                ->build();
        }
    }
}
