<?php

namespace App\Exports;

use App\Models\PurchaseOrder;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class PurchaseOrderExport implements FromCollection, WithHeadings, WithMapping
{
    protected $startDate;
    protected $endDate;

    public function __construct($startDate = null, $endDate = null)
    {
        $this->startDate = $startDate;
        $this->endDate = $endDate;
    }

    public function collection()
    {
        // Query untuk mengambil data Purchase Orders beserta relasi
        $query = PurchaseOrder::with(['supplier', 'purchaseRequest', 'detailorder.item.unit', 'detailorder.item.category', 'user']);

        // Filter berdasarkan tanggal jika parameter tersedia
        if ($this->startDate && $this->endDate) {
            $query->whereBetween('created_at', [$this->startDate, $this->endDate]);
        }

        return $query->get();
    }

    public function headings(): array
    {
        return [
            'Purchase Order ID',
            'Purchase Order No',
            'Purchase Request No',
            'Supplier Name',
            'Supplier Remark',

            'Date in House',
            'MO',  // Added MO
            'Style',  // Added Style
            'Category',  // New column for item category
            'Item Code',
            'Item Name',
            'Unit',
            'Color',
            'Size',
            'Quantity',

            'Price',
            'Total Price',
            'Currency',
            'Status',
            'User Name',
            'Created At',
            'Updated At',
        ];
    }

    public function map($purchaseOrder): array
    {
        $mappedData = [];
        foreach ($purchaseOrder->detailorder as $detail) {


            $mataUang = '';
            if ($purchaseOrder->supplier->remark == 'Local' || $purchaseOrder->supplier->remark == 'Lokal') {
                $mataUang = 'IDR'; // For lokal suppliers
            } elseif ($purchaseOrder->supplier->remark == 'Import') {
                $mataUang = 'USD'; // For import suppliers
            }



            $mappedData[] = [
                $purchaseOrder->id,
                $purchaseOrder->purchase_order_no,
                $purchaseOrder->purchaseRequest->purchase_request_no ?? '',
                $purchaseOrder->supplier->supplier_name ?? '',
                $purchaseOrder->supplier->remark ?? '',

                $purchaseOrder->date_in_house,
                $purchaseOrder->purchaseRequest->mo ?? '',  // MO
                $purchaseOrder->purchaseRequest->style ?? '',  // Style
                $detail->item->category->name ?? '',
                $detail->item->item_code ?? '',
                $detail->item->item_name ?? '',
                $detail->item->unit->unit_code ?? '',
                $detail->color,
                $detail->size,
                $detail->qty,

                $detail->price,
                $detail->total_price,
                $mataUang,
                $detail->status,
                $purchaseOrder->user->name ?? '-',  // User name
                $purchaseOrder->created_at,
                $purchaseOrder->updated_at,
            ];
        }
        return $mappedData;
    }
}