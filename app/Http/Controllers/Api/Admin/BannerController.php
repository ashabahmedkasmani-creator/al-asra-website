<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\BannerResource;
use App\Models\Banner;
use App\Services\AuditLogger;
use App\Services\ImageService;
use Illuminate\Http\Request;

class BannerController extends Controller
{
    public function __construct(private ImageService $images) {}

    public function index()
    {
        return BannerResource::collection(Banner::orderBy('sort_order')->get());
    }

    public function store(Request $request)
    {
        $banner = Banner::create($this->validated($request, true));
        $this->maybeImage($request, $banner);
        AuditLogger::record($request, 'banner.created', $banner, null, ['title' => $banner->title]);
        return (new BannerResource($banner->fresh()))->response()->setStatusCode(201);
    }

    public function update(Request $request, Banner $banner)
    {
        $banner->update($this->validated($request, false));
        $this->maybeImage($request, $banner);
        AuditLogger::record($request, 'banner.updated', $banner);
        return new BannerResource($banner->fresh());
    }

    public function destroy(Request $request, Banner $banner)
    {
        $this->images->delete($banner->image_path);
        AuditLogger::record($request, 'banner.deleted', $banner, ['title' => $banner->title]);
        $banner->delete();
        return response()->noContent();
    }

    private function maybeImage(Request $request, Banner $banner): void
    {
        if ($request->hasFile('image')) {
            $request->validate(['image' => ['file', 'mimes:jpg,jpeg,png,webp', 'max:4096', 'dimensions:max_width=5000,max_height=5000']]);
            $old = $banner->image_path;
            $banner->update(['image_path' => $this->images->store($request->file('image'), 'banners')]);
            $this->images->delete($old);
        }
    }

    private function validated(Request $request, bool $creating): array
    {
        $req = $creating ? 'required' : 'sometimes';
        $data = $request->validate([
            'title' => [$req, 'string', 'max:150'], 'subtitle' => ['nullable', 'string', 'max:255'],
            // Relative paths or http(s) only: blocks javascript: / data: URLs.
            'link_url' => ['nullable', 'string', 'max:255', 'regex:#^(/[A-Za-z0-9/_\-?=&%.]*|https?://[^\s]+)$#'],
            'button_text' => ['nullable', 'string', 'max:50'],
            'placement' => ['sometimes', 'in:home_hero,home_promo,announcement'],
            'sort_order' => ['sometimes', 'integer', 'min:0'], 'is_active' => ['sometimes', 'boolean'],
            'starts_at' => ['nullable', 'date'], 'ends_at' => ['nullable', 'date', 'after:starts_at'],
        ]);
        foreach (['title', 'subtitle', 'button_text'] as $f) {
            if (isset($data[$f])) $data[$f] = strip_tags($data[$f]);
        }
        return $data;
    }
}
