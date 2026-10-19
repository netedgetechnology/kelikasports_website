<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MediaAsset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaController extends Controller
{
    public function index(Request $request)
    {
        $query = MediaAsset::query()->latest();
        if ($request->filled('q')) {
            $term = $request->input('q');
            $query->where(fn ($builder) => $builder
                ->where('original_name', 'like', '%'.$term.'%')
                ->orWhere('alt_text', 'like', '%'.$term.'%')
                ->orWhere('caption', 'like', '%'.$term.'%'));
        }
        $media = $query->paginate(24)->withQueryString();

        return view('admin.media.index', compact('media'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'max:8192', 'mimes:jpg,jpeg,png,webp,pdf'],
            'alt_text' => ['nullable', 'string', 'max:250'],
            'caption' => ['nullable', 'string', 'max:500'],
        ]);

        $file = $data['file'];
        $extension = strtolower($file->getClientOriginalExtension());
        $filename = Str::uuid().'.'.$extension;
        $path = $file->storeAs('media/'.now()->format('Y/m'), $filename, 'public');

        MediaAsset::create([
            'disk' => 'public',
            'path' => $path,
            'original_name' => Str::limit($file->getClientOriginalName(), 240, ''),
            'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
            'size_bytes' => $file->getSize(),
            'alt_text' => $data['alt_text'] ?? null,
            'caption' => $data['caption'] ?? null,
            'uploaded_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Media uploaded.');
    }

    public function update(Request $request, MediaAsset $media)
    {
        $data = $request->validate([
            'original_name' => ['required', 'string', 'max:240'],
            'alt_text' => ['nullable', 'string', 'max:250'],
            'caption' => ['nullable', 'string', 'max:500'],
        ]);
        $media->update($data);

        return back()->with('success', 'Media details saved.');
    }

    public function destroy(MediaAsset $media)
    {
        $needle = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $media->path).'%';
        $isReferenced = DB::table('page_sections')->where('data', 'like', $needle)->exists()
            || DB::table('content_items')->where('data', 'like', $needle)->exists()
            || DB::table('settings')->where('value', 'like', $needle)->exists();

        if ($isReferenced) {
            return back()->withErrors(['media' => 'This asset appears to be in use. Replace its references before deleting it.']);
        }

        Storage::disk($media->disk)->delete($media->path);
        $media->delete();

        return back()->with('success', 'Media asset deleted.');
    }
}
