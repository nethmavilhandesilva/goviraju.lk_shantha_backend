<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Customer;
use App\Models\GrnEntry;
use App\Models\Sale;
use App\Models\Item;

class DashboardController extends Controller
{
   public function index()
{
    // Customers list
    $customers = Customer::select('short_name', 'name')->get();

    // GRN entries (latest first)
    $entries = GrnEntry::orderBy('txn_date', 'desc')->get();

    // Fetch items for pack_due values
    $items = Item::select('no', 'pack_due','pack_cost')->get(); // Add this line

    // 1. Fetch all sales with basic validation (must have ID and weight)
    $allSales = Sale::whereNotNull('id')
        ->whereNotNull('weight')
        ->get();

    // 2. Split into groups

    // "New" sales: not processed and not bill_printed
    $sales = $allSales->filter(function ($sale) {
        return ($sale->processed === 'N' || is_null($sale->processed))
            && is_null($sale->bill_printed);
    })->values();

    // Printed & unprinted groups
    $printedSales   = $allSales->where('bill_printed', 'Y')->values();
    $unprintedSales = $allSales->where('bill_printed', 'N')->values();

    // Total sum for "new" sales group
    $totalSum = $sales->sum(function ($s) {
        return $s->weight * $s->price_per_kg;
    });

    // Send data to the view
    return view(
        'reactdashboard.sales.entry',
        compact(
            'customers',
            'entries',
            'sales',
            'totalSum',
            'printedSales',
            'unprintedSales',
            'items' // Add this line
        )
    );
}
public function salesReport(Request $request)
{
    // Log all incoming parameters
    \Log::info('Sales Report Request:', [
        'all_params' => $request->all(),
        'transaction_type' => $request->transaction_type,
        'bill_status' => $request->bill_status,
        'start_date' => $request->start_date,
        'end_date' => $request->end_date,
        'supplier_code' => $request->supplier_code,
        'item_code' => $request->item_code,
        'customer_code' => $request->customer_code,
        'bill_no' => $request->bill_no
    ]);

    // Your existing logic...
    $useHistory = $request->filled('start_date') || $request->filled('end_date');

    $query = $useHistory
        ? SalesHistory::query()
        : Sale::query();

    $query->where('Processed', 'Y');

    // Apply filters...
    if ($request->filled('supplier_code')) {
        $query->where('supplier_code', $request->supplier_code);
    }

    if ($request->filled('item_code')) {
        $query->where('item_code', $request->item_code);
    }

    if ($request->filled('customer_code')) {
        $query->where('customer_code', $request->customer_code);
    }

    if ($request->filled('bill_no')) {
        $query->where('bill_no', $request->bill_no);
    }

    // New filter for transaction type (Credit/Cash)
    if ($request->filled('transaction_type')) {
        \Log::info('Applying transaction_type filter:', ['value' => $request->transaction_type]);
        
        if ($request->transaction_type === 'credit') {
            $query->where('credit_transaction', 'Y');
        } elseif ($request->transaction_type === 'cash') {
            $query->where('credit_transaction', 'N');
        }
    }

    // New filter for bill printed status
    if ($request->filled('bill_status')) {
        \Log::info('Applying bill_status filter:', ['value' => $request->bill_status]);
        
        if ($request->bill_status === 'printed') {
            $query->where('bill_printed', 'Y');
        } elseif ($request->bill_status === 'not_printed') {
            $query->where('bill_printed', 'N');
        }
    }

    if ($request->filled('start_date') && $request->filled('end_date')) {
        $query->whereBetween('Date', [$request->start_date, $request->end_date]);
    } elseif ($request->filled('start_date')) {
        $query->where('Date', '>=', $request->start_date);
    } elseif ($request->filled('end_date')) {
        $query->where('Date', '<=', $request->end_date);
    }

    $query->orderBy('id', 'DESC');
    
    // Log the SQL query for debugging
    \Log::info('SQL Query:', ['sql' => $query->toSql(), 'bindings' => $query->getBindings()]);
    
    $salesData = $query->get();
    
    \Log::info('Records found:', ['count' => $salesData->count()]);

    return response()->json([
        'salesData' => $salesData,
        'filters' => $request->all()
    ]);
}
}
