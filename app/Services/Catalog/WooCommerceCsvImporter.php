<?php

namespace App\Services\Catalog;

use App\Enums\Availability;
use App\Models\Category;
use App\Models\Product;
use App\Services\ImageService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class WooCommerceCsvImporter
{
    public function __construct(private ImageService $images) {}

    /** Import the standard WooCommerce product CSV export format. */
    public function import(string $path): array
    {
        $handle = fopen($path, 'rb');
        if (! $handle) {
            throw new \RuntimeException('The uploaded CSV file could not be opened.');
        }

        try {
            $first = fgets($handle);
            if ($first === false) {
                throw new \RuntimeException('The CSV file is empty.');
            }
            $delimiter = $this->detectDelimiter($first);
            rewind($handle);
            $headers = fgetcsv($handle, 0, $delimiter, '"', '');
            if (! is_array($headers) || count($headers) < 2) {
                throw new \RuntimeException('The CSV header row is missing or invalid.');
            }
            $headers = array_map(fn ($v) => $this->key((string) $v), $headers);
            if (! in_array('name', $headers, true) || ! in_array('regularprice', $headers, true)) {
                throw new \RuntimeException('This does not look like a WooCommerce product CSV. Required columns: Name and Regular price.');
            }

            $result = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0, 'errors' => []];
            $line = 1;
            while (($values = fgetcsv($handle, 0, $delimiter, '"', '')) !== false) {
                $line++;
                if ($values === [null] || $values === []) { $result['skipped']++; continue; }
                if ($line > 1001) {
                    $result['failed']++;
                    $result['errors'][] = ['row' => $line, 'message' => 'Import limit is 1,000 product rows per file.'];
                    break;
                }
                $row = [];
                foreach ($headers as $i => $header) $row[$header] = trim((string) ($values[$i] ?? ''));
                try {
                    if (($row['name'] ?? '') === '') { $result['skipped']++; continue; }
                    $type = strtolower(trim($row['type'] ?? 'simple'));
                    if ($type !== '' && $type !== 'simple') {
                        $result['skipped']++;
                        $result['errors'][] = ['row' => $line, 'name' => $row['name'], 'message' => 'Only simple products are imported. Variable products and variations need to be converted to simple products first.'];
                        continue;
                    }
                    $imported = DB::transaction(fn () => $this->importRow($row));
                    if ($imported['image_urls']) $this->syncImages($imported['product'], $imported['image_urls']);
                    $result[$imported['action']]++;
                } catch (Throwable $e) {
                    report($e);
                    $result['failed']++;
                    $result['errors'][] = ['row' => $line, 'name' => $row['name'] ?? '', 'message' => $this->safeMessage($e)];
                }
            }
            return $result;
        } finally {
            fclose($handle);
        }
    }

    private function importRow(array $r): array
    {
        $name = $this->plain($r['name'] ?? '', 200);
        $regular = $this->money($r['regularprice'] ?? $r['price'] ?? '');
        if ($name === '' || $regular <= 0) throw new \RuntimeException('Product name and a valid Regular price are required.');

        $sku = $this->plain($r['sku'] ?? '', 64);
        if ($sku === '') $sku = 'WP-'.strtoupper(substr(sha1(Str::slug($name)), 0, 12));
        $existing = Product::withTrashed()->where('sku', $sku)->first();
        $csvSlug = $this->plain($r['slug'] ?? '', 220);
        $slugBase = Str::slug($csvSlug !== '' ? $csvSlug : $name) ?: 'product';
        if (! $existing) $existing = Product::withTrashed()->where('slug', $slugBase)->first();

        $sale = $this->moneyOrNull($r['saleprice'] ?? '');
        if ($sale !== null && ($sale <= 0 || $sale >= $regular)) $sale = null;
        $categoryId = $this->categoryId($r['categories'] ?? '');
        $inStock = $this->truthy($r['instock'] ?? '1');
        $stockText = trim($r['stock'] ?? '');
        $manageStock = $this->truthy($r['managestock'] ?? '') || $stockText !== '';
        $stock = $stockText !== '' && is_numeric($stockText) ? max(0, (int) $stockText) : null;
        $availability = (! $inStock || ($manageStock && $stock !== null && $stock < 1))
            ? Availability::Unavailable->value
            : Availability::Available->value;
        $published = (! array_key_exists('published', $r) || $this->truthy($r['published']))
            && strtolower(trim($r['visibilityincatalog'] ?? 'visible')) !== 'hidden';
        $featured = $this->truthy($r['isfeatured'] ?? '0');

        $data = [
            'category_id' => $categoryId,
            'name' => $name,
            'sku' => $sku,
            'short_description' => $this->plain($r['shortdescription'] ?? '', 500) ?: null,
            'description' => $this->plain($r['description'] ?? '', 10000) ?: null,
            'price' => $regular,
            'sale_price' => $sale,
            'availability' => $availability,
            'track_inventory' => $manageStock,
            'stock_quantity' => $manageStock ? ($stock ?? 0) : null,
            'is_featured' => $featured,
            'show_on_home' => $featured,
        ];

        if ($existing) {
            $existing->restore();
            $data['slug'] = $csvSlug !== '' && $existing->slug !== $slugBase
                ? Product::uniqueSlug($slugBase, $existing->id)
                : $existing->slug;
            $existing->update($data);
            $product = $existing->fresh();
            $action = 'updated';
        } else {
            $data['slug'] = Product::uniqueSlug($slugBase);
            $product = Product::create($data);
            $action = 'created';
        }

        if ($published) $product->publish(); else $product->unpublish();
        return ['action' => $action, 'product' => $product, 'image_urls' => $this->imageUrls($r['images'] ?? '')];
    }

    private function categoryId(string $raw): ?int
    {
        if (trim($raw) === '') return null;
        // WooCommerce uses comma-separated categories and Parent > Child for hierarchy.
        $first = trim(explode(',', $raw)[0]);
        $parentId = null;
        foreach (preg_split('/\s*>\s*/', $first) ?: [] as $part) {
            $name = $this->plain($part, 120);
            if ($name === '') continue;
            $slug = Str::slug($name) ?: 'category';
            $category = Category::firstOrCreate(['slug' => $slug], [
                'parent_id' => $parentId, 'name' => $name, 'is_active' => true,
            ]);
            $parentId = $category->id;
        }
        return $parentId;
    }

    private function imageUrls(string $raw): array
    {
        return array_slice(array_values(array_filter(array_map('trim', explode(',', $raw)))), 0, 10);
    }

    private function syncImages(Product $product, array $urls): void
    {
        $saved = [];
        foreach ($urls as $url) {
            $path = $this->downloadImage($url);
            if ($path) $saved[] = $path;
        }
        if (! $saved) return;

        $oldPaths = $product->images()->pluck('path')->all();
        DB::transaction(function () use ($product, $saved) {
            $product->images()->delete();
            foreach ($saved as $i => $path) {
                $product->images()->create([
                    'path' => $path,
                    'alt_text' => $product->name,
                    'sort_order' => $i,
                    'is_primary' => $i === 0,
                ]);
            }
        });
        foreach ($oldPaths as $oldPath) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($oldPath);
        }
    }

    private function downloadImage(string $url): ?string
    {
        if (! filter_var($url, FILTER_VALIDATE_URL)) return null;
        $parts = parse_url($url);
        if (! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true) || empty($parts['host'])) return null;
        $host = strtolower($parts['host']);
        if ($host === 'localhost' || str_ends_with($host, '.local')) return null;
        $ip = filter_var($host, FILTER_VALIDATE_IP) ? $host : gethostbyname($host);
        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) return null;

        try {
            $response = Http::timeout(15)->withOptions(['allow_redirects' => false])->get($url);
            if (! $response->successful()) return null;
            $body = $response->body();
            if ($body === '' || strlen($body) > 5 * 1024 * 1024) return null;
            return $this->images->storeContents($body, 'products/imported');
        } catch (Throwable $e) {
            report($e);
            return null;
        }
    }

    private function detectDelimiter(string $line): string
    {
        $counts = [];
        foreach ([',', ';', "\t"] as $delimiter) $counts[$delimiter] = count(str_getcsv($line, $delimiter, '"', ''));
        arsort($counts);
        return (string) array_key_first($counts);
    }

    private function key(string $value): string
    {
        $value = preg_replace('/^\xEF\xBB\xBF/', '', $value) ?? $value;
        return strtolower(preg_replace('/[^a-z0-9]/i', '', trim($value)) ?? '');
    }

    private function plain(string $value, int $max): string
    {
        $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return Str::limit(trim(preg_replace('/\s+/', ' ', $value) ?? ''), $max, '');
    }

    private function money(string $value): float
    {
        $n = preg_replace('/[^0-9.\-]/', '', str_replace(',', '', $value));
        return is_numeric($n) ? (float) $n : 0;
    }

    private function moneyOrNull(string $value): ?float
    {
        return trim($value) === '' ? null : $this->money($value);
    }

    private function truthy(string $value): bool
    {
        return in_array(strtolower(trim($value)), ['1', 'yes', 'true', 'publish', 'published', 'instock'], true);
    }

    private function safeMessage(Throwable $e): string
    {
        $message = $e->getMessage();
        return strlen($message) > 220 ? substr($message, 0, 220).'…' : $message;
    }
}
