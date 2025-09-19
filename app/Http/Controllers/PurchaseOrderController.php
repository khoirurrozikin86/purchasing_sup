<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrder;
use Illuminate\Http\Request;
use App\Models\PurchaseRequest;
use App\Models\PurchaseOrderDetail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\PurchaseRequestDetail;
use Carbon\Carbon;
use App\Exports\PurchaseOrderExport;
use Maatwebsite\Excel\Facades\Excel;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use PDF;

class PurchaseOrderController extends Controller
{
    public function Allpurchaseorder()
    {
        return view('purchase_order.all_purchaseorder');
    }

	    public function GetpurchaseorderCountPO(){
        $purchaseCount = PurchaseOrder::count();

        return response()->json([
            'request' =>$purchaseCount,
         
        ]);
    }



    public function AddPurchaseorderid($id)
    {
        // Ambil data Purchase Request berdasarkan ID beserta detail yang statusnya kosong
        $purchaseRequest = PurchaseRequest::with(['detailrequest' => function ($query) {
            $query->whereNull('status')->orWhere('status', '');
        }, 'detailrequest.item.unit', 'cbd.details'])->findOrFail($id);

        // Ambil order_no dan sample_code dari relasi CBD
        $purchaseRequest->order_no = $purchaseRequest->cbd->order_no ?? null;
        $purchaseRequest->sample_code = $purchaseRequest->cbd->sample_code ?? null;
        $purchaseRequest->item = $purchaseRequest->cbd->item ?? null;

        // Kirimkan data ke view untuk ditampilkan
        return view('purchase_order.add_purchaseorderid', compact('purchaseRequest'));
    }

    public function Editpurchaseorder($id)
    {
        // Fetch the Purchase Order with details, supplier
        $purchaseOrder = PurchaseOrder::with(['detailorder.item.unit', 'supplier'])->findOrFail($id);



        $purchaseRequestId =  $purchaseOrder->purchase_request_id;
        $supplierId = $purchaseOrder->supplier_id;

        $purchaseRequest =  PurchaseRequestDetail::where('purchase_request_id', $purchaseRequestId)
            ->where('supplier_id', $supplierId)
            ->where('status', '')
            ->with('item.unit', 'supplier')
            ->get();





        return view('purchase_order.edit_purchaseorderid', compact('purchaseOrder', 'purchaseRequest'));
    }



 protected function generatePurchaseOrderNumber()
    {
        // Ambil tahun dan bulan saat ini
        $currentYear = date('Y');
        $currentMonth = date('n');  // Bulan dalam format numerik (1-12)
    
        // Ambil purchase order terakhir berdasarkan urutan
        // $latestOrder = PurchaseOrder::latest()->first();
 
 $latestOrder = PurchaseOrder::whereNull('deleted_at') // Abaikan data yang di-soft delete
    ->whereNotNull('purchase_order_no') // Hanya yang memiliki nomor PO valid
    ->orderBy('id', 'desc') // Urutkan berdasarkan ID terbesar yang valid
    ->first();
    
        // Default nomor urut
        $number = 1;
    
        if ($latestOrder) {
            // Ambil bulan dan tahun dari purchase_order_no terakhir
            $lastYear = substr($latestOrder->purchase_order_no, -8, 4);  // Mengambil tahun dari bagian terakhir nomor PO
            $lastMonthRoman = substr($latestOrder->purchase_order_no, 13, 2); // Mengambil bulan Roman dari nomor PO
    
            // Cek apakah tahun dari order terakhir sama dengan tahun sekarang
            if ($lastYear == $currentYear) {
                // Jika tahun sama, ambil nomor urut terakhir dan tambah 1
                $lastNumber = (int) substr($latestOrder->purchase_order_no, 5, 6); // Ambil nomor urut 6 digit
                $number = $lastNumber + 1;
            }
        }
    
        // Pastikan nomor urut 6 digit
        $formattedNumber = str_pad($number, 6, '0', STR_PAD_LEFT);
    
        // Konversi bulan ke format Roman
        $monthRoman = $this->convertToRoman($currentMonth);
    
        // Format nomor PO dan kembalikan hasilnya
        return sprintf('TIMW/%s/PO/%s/%d/SUP', $formattedNumber, $monthRoman, $currentYear);
    }
    
    protected function convertToRoman($month)
    {
        $map = [
            1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV',
            5 => 'V', 6 => 'VI', 7 => 'VII', 8 => 'VIII',
            9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII'
        ];
    
        return $map[$month];
    }
    







    protected function generatePurchaseOrderNumber1()
    {
        // Ambil purchase order terakhir berdasarkan urutan
        $latestOrder = PurchaseOrder::latest()->first();

        // Jika ada order sebelumnya, ambil nomor urutnya, tambahkan 1, dan pastikan 6 digit
        $number = $latestOrder ? (int) substr($latestOrder->purchase_order_no, 5, 6) + 1 : 1;

        // Pastikan nomor urut 6 digit
        $formattedNumber = str_pad($number, 6, '0', STR_PAD_LEFT);

        // Ambil tahun dan bulan dalam format Roman
        $year = date('Y');
        $month = $this->convertToRoman1(date('n'));

        // Format nomor PO dan kembalikan hasilnya
       return sprintf('TIMW/%s/PO/%s/%d/SUP', $formattedNumber, $month, $year);
    }

    protected function convertToRoman1($month)
    {
        $map = [
            1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV',
            5 => 'V', 6 => 'VI', 7 => 'VII', 8 => 'VIII',
            9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII'
        ];

        return $map[$month];
    }


  public function Storepurchaseorder1(Request $request)
    {
        // Validasi input request
        $request->validate([
            'purchase_request_id' => 'required|integer',
            'supplier_id' => 'required|integer',
            'delivery_at' => 'required|string',
            'terms' => 'required|string',
            'payment' => 'required|string',
            'applicant' => 'required|string',
            'allocation' => 'required|string',
            'approval' => 'required|string',
            'rule' => 'nullable|string',
            'status' => 'nullable|string',
            'details' => 'required|array|min:1', // Minimal 1 detail
            'details.*.item_id' => 'required|integer',
            'details.*.qty' => 'required|numeric',
            'details.*.price' => 'required|numeric',
        ]);
    
        // Mulai transaksi untuk menjaga konsistensi data
        DB::beginTransaction();
    
        try {
            // Cek jika ada kombinasi yang sama di purchase_order_details
            foreach ($request->details as $detailData) {
                $exists = PurchaseOrder::with(['detailorder' => function ($query) use ($detailData, $request) {
                    $query->where('item_id', $detailData['item_id'])
                          ->where(function($subQuery) use ($detailData) {
                              if (isset($detailData['color'])) {
                                  $subQuery->where('color', $detailData['color']);
                              } else {
                                  $subQuery->whereNull('color');
                              }
                              if (isset($detailData['size'])) {
                                  $subQuery->where('size', $detailData['size']);
                              } else {
                                  $subQuery->whereNull('size');
                              }
                          })
                          ->where('qty', $detailData['qty']);
                }])
                ->where('purchase_request_id', $request->purchase_request_id)
                ->where('supplier_id', $request->supplier_id)
                ->exists();
    
                if ($exists) {
                    return redirect()->route('all.purchaseorder')->with('message', 'Terjadi kesalahan, data gagal disimpan, duplikat PO!');
                }
            }
    
            // Simpan data ke purchase_orders
            $purchaseOrder = PurchaseOrder::create([
                'purchase_order_no' => $this->generatePurchaseOrderNumber(), // Fungsi untuk generate purchase_order_no
                'purchase_request_id' => $request->purchase_request_id,
                'supplier_id' => $request->supplier_id,
                'date_in_house' => $request->request_in_house,
                'delivery_at' => $request->delivery_at,
                'terms' => $request->terms,
                'payment' => $request->payment,
                'ship_mode' => $request->shipment_mode,
                'applicant' => $request->applicant,
                'allocation' => $request->allocation,
                'approval' => $request->approval,
                'quotation_no' => $request->quotation_no,
                'quotation_file' => '',
    
                'subtotal' => $request->sub_total,
                'rounding' => $request->rounding ?? 0,
                'discount' => $request->discount ?? 0,
                'vat' => $request->tax,
                'vat_amount' => $request->tax_end,
                'grand_total' => $request->grand_total_end,
                'purchase_amount' => $request->purchase_amount_end,
                'note1' => $request->note1,
                'note2' => $request->note2,
                'rule' => $request->rule,
                'status' => 'po',
                'revision_no' => 0,
                'user_id' => Auth::id(), // Ambil user_id dari user yang login
            ]);
    
            // Simpan detail pembelian ke purchase_order_details
            foreach ($request->details as $detailData) {
                // Hitung total_price jika belum ada
                $totalPrice = $detailData['total_price'] ?? ($detailData['qty'] * $detailData['price']);
                
                // Simpan detail
                $purchaseOrderDetail = PurchaseOrderDetail::create([
                    'purchase_order_id' => $purchaseOrder->id,
                    'item_id' => $detailData['item_id'],
                    'color' => $detailData['color'] ?? null,
                    'size' => $detailData['size'] ?? null,
                    'qty' => $detailData['qty'],
                    'price' => $detailData['price'],
                    'total_price' => $totalPrice,
                    'status' => '',
                    'remark' => $detailData['remark'] ?? null,
                ]);
    
                // Update status pada PurchaseRequestDetail
                PurchaseRequestDetail::where('purchase_request_id', $request->purchase_request_id)
                    ->where('item_id', $detailData['item_id'])
                    ->where(function($query) use ($detailData) {
                        if (isset($detailData['color'])) {
                            $query->where('color', $detailData['color']);
                        } else {
                            $query->whereNull('color');
                        }
                        if (isset($detailData['size'])) {
                            $query->where('size', $detailData['size']);
                        } else {
                            $query->whereNull('size');
                        }
                    })
                    ->update(['status' => 'po']);
            }
    
            // Commit transaksi
            DB::commit();
    
            return redirect()->route('all.purchaseorder')->with('message', 'Purchase berhasil disimpan.');
        } catch (\Exception $e) {
            // Rollback transaksi jika terjadi error
            DB::rollBack();
            return redirect()->route('all.purchaseorder')->with('message', 'Terjadi kesalahan, data gagal disimpan, duplikat PO!');
        }
    }

    public function Storepurchaseorder(Request $request)
    {
        // Validasi input request
        $request->validate([
            'purchase_request_id' => 'required|integer',
            'supplier_id' => 'required|integer',
            'delivery_at' => 'required|string',
            'terms' => 'required|string',
            'payment' => 'required|string',
            'applicant' => 'required|string',
            'allocation' => 'required|string',
            'approval' => 'required|string',
            'rule' => 'nullable|string',
            'status' => 'nullable|string',
            'details' => 'required|array|min:1', // Minimal 1 detail
            'details.*.item_id' => 'required|integer',
            'details.*.qty' => 'required|numeric',
            'details.*.price' => 'required|numeric',
        ]);




        // Simpan data ke purchase_orders
        $purchaseOrder = PurchaseOrder::create([
            'purchase_order_no' => $this->generatePurchaseOrderNumber(), // Fungsi untuk generate purchase_order_no
            'purchase_request_id' => $request->purchase_request_id,
            'supplier_id' => $request->supplier_id,
            'date_in_house' => $request->request_in_house,
            'delivery_at' => $request->delivery_at,
            'terms' => $request->terms,
            'payment' => $request->payment,
            'ship_mode' => $request->shipment_mode,
            'applicant' => $request->applicant,
            'allocation' => $request->allocation,
            'approval' => $request->approval,
            'quotation_no' => $request->quotation_no,
            'quatition_file' => '',

            'subtotal' => $request->sub_total,
            'dpp' => $request->dpp,
            'rounding' => $request->rounding ?? 0,
            'discount' => $request->pph,
            'catatan1' => $request->discount,
            'vat' => $request->tax,
            'vat_amount' => $request->tax_end,
            'grand_total' => $request->grand_total_end,
            'purchase_amount' => $request->purchase_amount_end,
            'remarksx' => $request->pph_end,
            'note1' => $request->note1,
            'note2' => $request->note2,
            'rule' => $request->rule,
            'status' => 'po',
            'revision_no' => 0,
            'user_id' => Auth::id(), // Ambil user_id dari user yang login
        ]);

        // Simpan detail pembelian ke purchase_order_details
        foreach ($request->details as $detailData) {
            PurchaseOrderDetail::create([
                'purchase_order_id' => $purchaseOrder->id,
                'item_id' => $detailData['item_id'],
                'color' => $detailData['color'] ?? null,
                'size' => $detailData['size'] ?? null,
                'qty' => $detailData['qty'],
                'price' => $detailData['price'],
                'total_price' => $detailData['total_price'] ?? ($detailData['qty'] * $detailData['price']), // Hitung total_price jika tidak disediakan
                'status' => '',
                'remark' => $detailData['remark'] ?? null,
            ]);


            // Update status pada PurchaseRequestDetail
            PurchaseRequestDetail::where('purchase_request_id', $request->purchase_request_id)
                ->where('item_id', $detailData['item_id'])
                ->where(function ($query) use ($detailData) {
                    if (isset($detailData['color'])) {
                        $query->where('color', $detailData['color']);
                    } else {
                        $query->whereNull('color');
                    }
                    if (isset($detailData['size'])) {
                        $query->where('size', $detailData['size']);
                    } else {
                        $query->whereNull('size');
                    }
                })
                ->update(['status' => 'po']);
        }





        return redirect()->route('all.purchaseorder')->with('message', 'Purchase berhasil disimpan.');
    }

public function Getpurchaseorder(Request $request)
{
    if ($request->ajax()) {
        $query = PurchaseOrder::with(['purchaseRequest', 'detailorder.item.unit', 'detailorder.item.category', 'supplier', 'user'])
            ->orderBy('created_at', 'desc');

        // Filter berdasarkan startDate dan endDate
        if ($request->has('startDate') && $request->has('endDate')) {
            $startDate = $request->startDate;
            $endDate = $request->endDate;

            // Filter tanggal pada kolom created_at (atau sesuaikan dengan kolom yang relevan)
            $query->whereBetween('created_at', [$startDate, $endDate]);
        }

        $data = $query->get();

        return datatables()->of($data)
            ->addIndexColumn()
            ->addColumn('supplier_name', function ($row) {
                return $row->supplier->supplier_name ?? '';  // Pastikan supplier_name ada
            })
            ->addColumn('supplier_remark', function ($row) {
                return $row->supplier->remark ?? '';  // Display the supplier remark below supplier_name
            })
            ->addColumn('item_details', function ($row) {
                $itemDetails = [];
                foreach ($row->detailorder as $detail) {
                    // Pastikan item tidak null
                    $item = $detail->item;

                    if ($item) {
                        $itemName = strlen($item->item_name) > 25 ? 
                            substr($item->item_name, 0, 25) . '...' : 
                            $item->item_name;

                        $itemDetails[] = [
                            'category' => $item->category->name ?? '',  // Pastikan category ada
                            'item_code' => $item->item_code ?? '',  // Pastikan item_code ada
                            'item_name' =>  $itemName,
                            'unit_code' => $item->unit->unit_code ?? '',  // Pastikan unit_code ada
                            'color' => $detail->color ?? '',
                            'size' => $detail->size ?? '',
                            'qty' => $detail->qty ?? 0,  // Pastikan qty ada
                            'price' => $detail->price ?? 0,  // Pastikan price ada
                            'status' => $detail->status ?? '',
                        ];
                    } else {
                        // Jika item tidak ada, tambahkan data default untuk item_details
                        $itemDetails[] = [
                            'category' => '',
                            'item_code' => '',
                            'item_name' => '',
                            'unit_code' => '',
                            'color' => $detail->color ?? '',
                            'size' => $detail->size ?? '',
                            'qty' => $detail->qty ?? 0,
                            'price' => $detail->price ?? 0,
                            'status' => $detail->status ?? '',
                        ];
                    }
                }
                return $itemDetails;
            })
            ->addColumn('purchase_request_no', function ($row) {
                return $row->purchaseRequest->purchase_request_no ?? '';  // Pastikan purchase_request_no ada
            })
            ->addColumn('mo', function ($row) {
                return $row->purchaseRequest->mo . '|' . $row->purchaseRequest->style ?? '';  // Pastikan mo dan style ada
            })
            ->addColumn('user_name', function ($row) {
                return $row->user->name ?? '-';  // Pastikan user_name ada
            })
            ->addColumn('action', function ($row) {
                $hasStatus = false;
                foreach ($row->detailorder as $detail) {
                    if (!empty($detail->status)) {
                        $hasStatus = true;
                        break;
                    }
                }

                $editButton = $hasStatus ?
                    '<a href="javascript:void(0)" class="dropdown-item text-muted disabled"> &nbsp; Edit</a>' :
                    '<a href="/edit/purchaseorder/' . $row->id . '" class="dropdown-item text-primary"> &nbsp; Edit</a>';

                $deleteButton = $hasStatus ?
                    '<a href="javascript:void(0)" class="dropdown-item text-muted disabled"> &nbsp; Delete</a>' :
                    '<a href="javascript:void(0)" class="dropdown-item text-danger deletePurchaseorder" data-id="' . $row->id . '"> &nbsp; Delete</a>';

                return '<div class="d-flex align-items-center justify-content-between flex-wrap">
                          <div class="d-flex align-items-center">
                              <div class="d-flex align-items-center">
                                  <div class="actions dropdown">
                                      <a href="#" data-bs-toggle="dropdown"> ••• </a>
                                      <div class="dropdown-menu" role="menu">
                                          ' . $editButton . '
                                          ' . $deleteButton . '
                                          <a href="/pdf/purchaseorder/' . $row->id . '" class="dropdown-item text-info" target="_blank"> &nbsp; View PDF</a>
                                      </div>
                                  </div>
                              </div> 
                          </div>
                      </div>';
            })
            ->rawColumns(['purchase_request_no', 'action'])
            ->make(true);
    }
}




    public function Getpurchaseorderxx(Request $request)
    {
        if ($request->ajax()) {
            $query = PurchaseOrder::with(['purchaseRequest', 'detailorder.item.unit', 'detailorder.item.category', 'supplier', 'user'])->orderBy('created_at', 'desc');

            // Filter berdasarkan startDate dan endDate
            if ($request->has('startDate') && $request->has('endDate')) {
                $startDate = $request->startDate;
                $endDate = $request->endDate;

                // Filter tanggal pada kolom created_at (atau sesuaikan dengan kolom yang relevan)
                $query->whereBetween('created_at', [$startDate, $endDate]);
            }

            $data = $query->get();

            return datatables()->of($data)
                ->addIndexColumn()
                ->addColumn('supplier_name', function ($row) {
                    return $row->supplier->supplier_name;
                })
                ->addColumn('supplier_remark', function ($row) {
                    return $row->supplier->remark ?? '';  // Display the supplier remark below supplier_name
                })

                ->addColumn('item_details', function ($row) {
                    $itemDetails = [];
                    foreach ($row->detailorder as $detail) {
                        $itemName = strlen($detail->item->item_name) > 25 ?
                            substr($detail->item->item_name, 0, 25) . '...' :
                            $detail->item->item_name;


                        $itemDetails[] = [
                            'category' => $detail->item->category->name ?? '',
                            'item_code' => $detail->item->item_code,
                            'item_name' =>  $itemName,
                            'unit_code' => $detail->item->unit->unit_code,
                            'color' => $detail->color ?? '',
                            'size' => $detail->size ?? '',
                            'qty' => $detail->qty,
                            'price' => $detail->price,
                            'status' => $detail->status,

                        ];
                    }
                    return $itemDetails;
                })
                ->addColumn('purchase_request_no', function ($row) {
                    return $row->purchaseRequest->purchase_request_no;
                })
                ->addColumn('mo', function ($row) {
                    return $row->purchaseRequest->mo . '|' . $row->purchaseRequest->style;
                })

                ->addColumn('user_name', function ($row) {
                    // Assuming the purchase order is linked to the user who created it
                    return $row->user->name ?? '-'; // Assuming 'name' is the field for user name
                })

                ->addColumn('action', function ($row) {
                    $hasStatus = false;
                    foreach ($row->detailorder as $detail) {
                        if (!empty($detail->status)) {
                            $hasStatus = true;
                            break;
                        }
                    }

                    $editButton = $hasStatus ?
                        '<a href="javascript:void(0)" class="dropdown-item text-muted disabled"> &nbsp; Edit</a>' :
                        '<a href="/edit/purchaseorder/' . $row->id . '" class="dropdown-item text-primary"> &nbsp; Edit</a>';

                    $deleteButton = $hasStatus ?
                        '<a href="javascript:void(0)" class="dropdown-item text-muted disabled"> &nbsp; Delete</a>' :
                        '<a href="javascript:void(0)" class="dropdown-item text-danger deletePurchaseorder" data-id="' . $row->id . '"> &nbsp; Delete</a>';

                    return '<div class="d-flex align-items-center justify-content-between flex-wrap">
                              <div class="d-flex align-items-center">
                                  <div class="d-flex align-items-center">
                                      <div class="actions dropdown">
                                          <a href="#" data-bs-toggle="dropdown"> ••• </a>
                                          <div class="dropdown-menu" role="menu">
                                              ' . $editButton . '
                                              ' . $deleteButton . '
                                              <a href="/pdf/purchaseorder/' . $row->id . '" class="dropdown-item text-info" target="_blank"> &nbsp; View PDF</a>
                                          </div>
                                      </div>
                                  </div> 
                              </div>
                          </div>';
                })
                ->rawColumns(['purchase_request_no', 'action'])
                ->make(true);
        }
    }

    public function GetpurchaseorderCount()
    {
        $purchaseCount = PurchaseOrderDetail::count();

        return response()->json([
            'request' => $purchaseCount,

        ]);
    }

    public function Updatepurchaseorder(Request $request, $id)
    {
        // Validasi input request
        $request->validate([
            'purchase_request_id' => 'required|integer',
            'supplier_id' => 'required|integer',
            'delivery_at' => 'required|string',

            'applicant' => 'required|string',
            'allocation' => 'required|string',
            'approval' => 'required|string',
            'rule' => 'nullable|string',
            'status' => 'nullable|string',
            'details' => 'required|array|min:1', // Minimal 1 detail
            'details.*.item_id' => 'required|integer',
            'details.*.qty' => 'required',
            'details.*.price' => 'required',
        ]);

        // Ambil purchase order yang akan diupdate
        $purchaseOrder = PurchaseOrder::findOrFail($id);

        // Ambil purchase request terkait
        $purchaseRequest = PurchaseRequest::findOrFail($request->purchase_request_id);

        // Ambil semua item_id yang ada dalam request details
        $detailItemIds = collect($request->details)->pluck('item_id')->toArray();

        // Inisialisasi array untuk menyimpan deleted detail IDs
        $deletedDetailIds = [];

        // Hapus semua detail terkait dari purchase order yang ada
        foreach ($purchaseOrder->detailorder as $detail) {
            $deletedDetailIds[] = $detail->id;
            $detail->delete();

            // Update status pada PurchaseRequestDetail yang terkait dengan item yang dihapus
            PurchaseRequestDetail::where('purchase_request_id', $purchaseRequest->id)
                ->where('item_id', $detail->item_id)
                ->where(function ($query) use ($detail) {
                    if ($detail->color) {
                        $query->where('color', $detail->color);
                    } else {
                        $query->whereNull('color');
                    }
                    if ($detail->size) {
                        $query->where('size', $detail->size);
                    } else {
                        $query->whereNull('size');
                    }
                })
                ->update(['status' => '']);
        }

        // Simpan detail pembelian ke purchase_order_details
        foreach ($request->details as $detailData) {
            $newDetail = PurchaseOrderDetail::create([
                'purchase_order_id' => $purchaseOrder->id,
                'item_id' => $detailData['item_id'],
                'color' => $detailData['color'] ?? null,
                'size' => $detailData['size'] ?? null,
                'qty' => $detailData['qty'],
                'price' => $detailData['price'],
                'total_price' => $detailData['total_price'],
                'status' => '',
                'remark' => $detailData['remark'] ?? null,
            ]);

            // Update status pada PurchaseRequestDetail yang sesuai
            PurchaseRequestDetail::where('purchase_request_id', $purchaseRequest->id)
                ->where('item_id', $detailData['item_id'])
                ->where(function ($query) use ($detailData) {
                    if (isset($detailData['color'])) {
                        $query->where('color', $detailData['color']);
                    } else {
                        $query->whereNull('color');
                    }
                    if (isset($detailData['size'])) {
                        $query->where('size', $detailData['size']);
                    } else {
                        $query->whereNull('size');
                    }
                })
                ->update(['status' => 'po']);
        }



        // Update data pada purchase order
        $purchaseOrder->update([
            'purchase_request_id' => $request->purchase_request_id,
            'purchase_order_no' => $request->purchase_order_no,
            'supplier_id' => $request->supplier_id,
            'date_in_house' => $request->request_in_house,
            'delivery_at' => $request->delivery_at,
            'terms' => $request->terms,
            'payment' => $request->payment,
            'ship_mode' => $request->shipment_mode,
            'applicant' => $request->applicant,
            'allocation' => $request->allocation,
            'approval' => $request->approval,
            'quotation_no' => $request->quotation_no,
            'quatition_file' => '',

            'subtotal' => $request->sub_total,
            'dpp' => $request->dpp,
            'rounding' => $request->rounding ?? 0,
            'discount' => $request->pph,
            'catatan1' => $request->discount ?? 0,
            'vat' => $request->tax,
            'vat_amount' => $request->tax_end,
            'grand_total' => $request->grand_total_end,
            'purchase_amount' => $request->purchase_amount_end,
            'note1' => $request->note1,
            'note2' => $request->note2,
            'rule' => $request->rule,
            'status' => 'po',
            'remarksx' => $request->pph_end,
            'revision_no' => $request->revision_no,
           'created_at' => $request->created_at,

            'user_id' => Auth::id(), // Ambil user_id dari user yang login
        ]);

        // Kembalikan ke halaman yang sesuai setelah selesai menyimpan
        return redirect()->route('all.purchaseorder')->with('message', 'Purchase Order berhasil diperbarui.');

        //return response
        return response()->json([
            'success' => true,
            'message' => 'Data Berhasil Disimpan!',
            'data'    => $post,
            'alert-type' => 'success'
        ]);
    }




    public function ExportPDF($id)
    {
        // $purchaseorder = PurchaseOrder::with(['detailorder.item.unit', 'supplier'])->findOrFail($id);
        // $pdf = Pdf::loadView('purchase_order.print', compact('purchaseorder'));
        // return $pdf->stream('purchase_order_' . $id . '.pdf');



        // Ambil PurchaseOrder dengan relasi detailorder, item, unit, dan supplier
        $purchaseorder = PurchaseOrder::with(['purchaseRequest', 'detailorder.item.unit', 'supplier'])->findOrFail($id);

        // Pastikan PurchaseOrder dan Supplier ditemukan
        if (!$purchaseorder) {
            abort(404);
        }

        // Ambil remark dari supplier
        $supplierRemark = $purchaseorder->supplier->remark;

            $qrCode = QrCode::size(80)->generate($purchaseorder->purchase_order_no);
    
            // Konversi QR Code ke format base64 agar bisa diembed dalam PDF
            $qrCodeBase64 = base64_encode($qrCode);



        // Pilih view berdasarkan remark supplier
        $viewName = ($supplierRemark === 'Import') ? 'purchase_order.print_import' : 'purchase_order.print_local';

        // Load view yang sesuai dengan remark
        $pdf = \PDF::loadView($viewName, compact('purchaseorder', 'qrCodeBase64'));
    
    	  $purchaseOrderNo = $purchaseorder->purchase_order_no; // pastikan ini sesuai dengan relasi dan field yang ada
   		  $supplierName = $purchaseorder->supplier->supplier_name; // pastikan ini sesuai dengan relasi dan field yang ada

        // Stream atau unduh PDF
        return $pdf->stream('purchase_order_' . $purchaseOrderNo . '_' . $supplierName . '.pdf');
    }


public function Deletepurchaseorder(Request $request, $id)
{
   
        // Hapus Purchase Order beserta detailnya
        $purchaseOrder = PurchaseOrder::findOrFail($id);

        // Mengembalikan status item menjadi kosong di PurchaseRequestDetail untuk setiap detail
        foreach ($purchaseOrder->detailorder as $detail) {
            PurchaseRequestDetail::where('purchase_request_id', $purchaseOrder->purchase_request_id)
                ->where('item_id', $detail->item_id)
                 ->where('size', $detail->size)
                ->where('color', $detail->color)
                ->update(['status' => null]); 
        }

        // Hapus semua PurchaseOrderDetail terkait
        $purchaseOrder->detailorder()->delete();
        $purchaseOrder->delete();

        return response()->json([
            'success' => true,
            'message' => 'Purchase Order dan detail berhasil dihapus!',
        ]);
    
}








    public function Deletepurchaseorder0(Request $request, $id)
    {
        if ($request->has('detail_id')) {
            // Hapus detail berdasarkan ID detail
            $detail = PurchaseOrderDetail::findOrFail($request->input('detail_id'));

            // Mengembalikan status item menjadi kosong di PurchaseRequestDetail
            PurchaseRequestDetail::where('purchase_request_id', $detail->purchase_order->purchase_request_id)
                ->where('item_id', $detail->item_id)
                ->where(function ($query) use ($detail) {
                    if ($detail->color) {
                        $query->where('color', $detail->color);
                    } else {
                        $query->whereNull('color');
                    }
                    if ($detail->size) {
                        $query->where('size', $detail->size);
                    } else {
                        $query->whereNull('size');
                    }
                    $query->where('qty', $detail->qty);
                })
                ->update(['status' => '']);

            $detail->delete();

            return response()->json([
                'success' => true,
                'message' => 'Detail berhasil dihapus!',
            ]);
        } else {
            // Hapus Purchase Order beserta detailnya
            $purchaseOrder = PurchaseOrder::findOrFail($id);

            // Mengembalikan status item menjadi kosong di PurchaseRequestDetail untuk setiap detail
            foreach ($purchaseOrder->detailorder as $detail) {
                PurchaseRequestDetail::where('purchase_request_id', $purchaseOrder->purchase_request_id)
                    ->where('item_id', $detail->item_id)
                    ->where(function ($query) use ($detail) {
                        if ($detail->color) {
                            $query->where('color', $detail->color);
                        } else {
                            $query->whereNull('color');
                        }
                        if ($detail->size) {
                            $query->where('size', $detail->size);
                        } else {
                            $query->whereNull('size');
                        }
                        $query->where('qty', $detail->qty);
                    })
                    ->update(['status' => '']);
            }

            $purchaseOrder->detailorder()->delete();
            $purchaseOrder->delete();

            return response()->json([
                'success' => true,
                'message' => 'Purchase Order dan detail berhasil dihapus!',
            ]);
        }
    }

    public function Getpurchaseordersupplier()
    {
        $suppliersx = PurchaseOrder::with(['detailorder' => function ($query) {
            $query->whereNull('status');
        }, 'detailorder.item.unit', 'supplier' => function ($query) {
            $query->select('id', 'supplier_name', 'supplier_address', 'supplier_person', 'remark');
        }])
            ->whereHas('detailorder', function ($query) {
                $query->whereNull('status')
                    ->orWhere('status', '');
            })

            ->get();

        $suppliers = $suppliersx->pluck('supplier')->unique('id')->values();

        return response()->json($suppliers);
    }


    public function Getpurchaseorderitem(Request $request)
    {
        $supplierId = $request->input('id2');

        $items = PurchaseOrder::with(['detailorder' => function ($query) {
            $query->whereNull('remark')
                ->orWhere('remark', '');
        }, 'detailorder.item.unit', 'supplier'])
            ->where('supplier_id', $supplierId)
            ->get();

        return response()->json($items);
    }



    public function Getpurchaseorderid($original_no)
    {
        // Ambil data PurchaseOrderDetail berdasarkan supplier_id yang diberikan
        $purchaseOrderItems = PurchaseOrder::with(['purchaseRequest', 'detailorder.item.unit', 'supplier'])
            ->where('supplier_id', $original_no) // langsung mencari dengan supplier_id
            ->get();

        // Jika tidak ditemukan data, kembalikan response kosong
        if ($purchaseOrderItems->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No items found for the given supplier.',
                'data' => []
            ], 404);
        }

        $formattedItems = $purchaseOrderItems->map(function ($detail) {
            return [
                'item_code'      => $detail->item_code,
                'item_name'      => $detail->detailorder->item_name,
                'supplier_name'  => $detail->purchaseOrder->supplier->supplier_name,
                'po'             => $detail->purchaseOrder->purchase_order_no, // Adjust field name as per your schema
                'color_code'     => $detail->color,
                'color_name'     => $detail->color_name ?? '', // Ensure these fields exist
                'size'           => $detail->size ?? '',
                'mo'             => $detail->purchaseOrder->purchaseRequest->mo ?? '', // Adjust as needed
                'qty'            => $detail->qty,
            ];
        });

        // Kembalikan data dalam bentuk JSON
        return response()->json([
            'success' => true,
            'message' => 'Items found successfully.',
            'data' => $formattedItems
        ]);
    }


    public function getSupplierPOItems($supplier_id)
    {
        // Ambil data PurchaseOrderDetail berdasarkan supplier_id yang diberikan
        $purchaseOrderItems = PurchaseOrderDetail::with(['item.unit', 'purchaseOrder'])
            ->whereHas('purchaseOrder', function ($query) use ($supplier_id) {
                $query->where('supplier_id', $supplier_id);
            })
            ->get();

        // Jika tidak ditemukan data, kembalikan response kosong
        if ($purchaseOrderItems->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No items found for the given supplier.',
                'data' => []
            ], 404);
        }

        // Kembalikan data dalam bentuk JSON
        return response()->json([
            'success' => true,
            'message' => 'Items found successfully.',
            'data' => $purchaseOrderItems
        ]);
    }


    public function Exportpurchaseorder(Request $request)
    {
        $startDate = $request->query('startDate');
        $endDate = $request->query('endDate');

        return Excel::download(new PurchaseOrderExport($startDate, $endDate), 'purchase_orders.xlsx');
    }
}
