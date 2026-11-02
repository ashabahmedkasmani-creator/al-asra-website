<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use App\Services\Catalog\WooCommerceCsvImporter;
use Illuminate\Http\Request;
use Throwable;

class ProductImportController extends Controller
{
    public function __invoke(Request $request, WooCommerceCsvImporter $importer)
    {
        $data = $request->validate([
            'csv' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
        ]);

        try {
            $result = $importer->import($data['csv']->getRealPath());
        } catch (Throwable $e) {
            report($e);
            return response()->json([
                'message' => $e instanceof \RuntimeException ? $e->getMessage() : 'The CSV could not be imported. Check that it is a WooCommerce product export.',
            ], 422);
        }

        AuditLogger::record($request, 'products.csv_imported', null, null, [
            'created' => $result['created'], 'updated' => $result['updated'],
            'skipped' => $result['skipped'], 'failed' => $result['failed'],
        ]);

        return response()->json(['message' => 'CSV import finished.', 'data' => $result]);
    }
}
