<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessLeadImport;
use App\Models\LeadImport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class LeadImportController extends Controller
{
    public function index()
    {
        return view('imports.index', ['imports' => LeadImport::latest()->paginate(20)]);
    }

    public function preview(Request $request)
    {
        $request->validate(['csv' => ['required', 'file', 'mimes:csv,txt', 'max:102400']]);
        $path = $request->file('csv')->store('imports');
        $file = new \SplFileObject(Storage::path($path));
        $file->setFlags(\SplFileObject::READ_CSV | \SplFileObject::SKIP_EMPTY);
        $headers = array_map('trim', $file->fgetcsv() ?: []);
        $rows = [];
        while (! $file->eof() && count($rows) < 5) {
            $row = $file->fgetcsv();
            if ($row && $row !== [null]) {
                $rows[] = $row;
            }
        }
        session(['import_path' => $path, 'import_name' => $request->file('csv')->getClientOriginalName()]);

        return view('imports.map', ['headers' => $headers, 'rows' => $rows, 'fields' => ProcessLeadImport::FIELDS]);
    }

    public function store(Request $request)
    {
        abort_unless(session('import_path'), 422, 'Upload a CSV first.');
        $mapping = array_filter($request->validate(['mapping' => ['required', 'array'], 'mapping.*' => ['nullable', 'string']])['mapping']);
        $import = LeadImport::create(['user_id' => $request->user()->id, 'original_filename' => session('import_name'), 'stored_path' => session('import_path'), 'column_mapping' => $mapping, 'status' => 'queued']);
        ProcessLeadImport::dispatch($import->id);
        session()->forget(['import_path', 'import_name']);

        return redirect()->route('imports.index')->with('success', 'Import queued. Run the queue worker to process it.');
    }
}
