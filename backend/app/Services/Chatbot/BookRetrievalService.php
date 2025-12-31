<?php

namespace App\Services\Chatbot;

use App\Models\Book;

class BookRetrievalService
{
    /**
     * Strategy:
     * - AUTO: decide based on filters + message
     * - KEYWORD: keyword scoring (title/author/category/description)
     * - FILTER_ONLY: ignore keyword sentence, use filters + sort
     */
    public function search(string $message, array $filters, int $limit = 8, string $strategy = 'AUTO'): array
    {
        $lower = mb_strtolower($message);

        // detect intent signals
        $hasPrice = isset($filters['minPrice']) || isset($filters['maxPrice']);
        $hasAge = isset($filters['readingAge']);
        $hasCategory = !empty($filters['category']);

        $meaningfulKeyword = $this->hasMeaningfulKeyword($lower, $filters);

        if ($strategy === 'AUTO') {
            // If user mainly asked by price/age/category without a real topic keyword, do FILTER_ONLY
            if (($hasPrice || $hasAge || $hasCategory) && !$meaningfulKeyword) {
                $strategy = 'FILTER_ONLY';
            } else {
                $strategy = 'KEYWORD';
            }
        }

        // Tiered fallback policy: keep PRICE first, then CATEGORY, then AGE
        $tiers = $this->buildFallbackTiers($filters);

        foreach ($tiers as $tierFilters) {
            $q = Book::query();
            $this->applyFilters($q, $tierFilters);

            if ($strategy === 'KEYWORD' && $meaningfulKeyword) {
                $rows = $this->runKeywordQuery($q, $message, $limit);
            } else {
                $rows = $this->runFilterOnlyQuery($q, $limit);
            }

            if ($rows->count() > 0) {
                return $this->mapRows($rows);
            }
        }

        // Final fallback: just return something available
        $rows = Book::query()
            ->orderByRaw("CASE WHEN quantity > 0 THEN 0 ELSE 1 END")
            ->orderByDesc('discount')
            ->orderBy('price', 'asc')
            ->limit($limit)
            ->get(['id','title','author','category','price','discount','quantity','description','reading_age']);

        return $this->mapRows($rows);
    }

    private function applyFilters($query, array $filters): void
    {
        if (!empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        if (!empty($filters['language'])) {
            $query->where('language', $filters['language']);
        }

        if (isset($filters['minPrice'])) {
            $query->where('price', '>=', (float)$filters['minPrice']);
        }

        if (isset($filters['maxPrice'])) {
            $query->where('price', '<=', (float)$filters['maxPrice']);
        }

        if (isset($filters['readingAge'])) {
            $query->where('reading_age', '<=', (int)$filters['readingAge']);
        }
    }

    /**
     * Tiered fallback (đúng ý bạn: hỏi giá thì ưu tiên giữ giá)
     */
    private function buildFallbackTiers(array $filters): array
    {
        $tiers = [];

        // Tier 1: strict (all provided)
        $tiers[] = $filters;

        // Tier 2: drop AGE first
        if (isset($filters['readingAge'])) {
            $f = $filters;
            unset($f['readingAge']);
            $tiers[] = $f;
        }

        // Tier 3: if has category and price, drop category (keep price)
        if (!empty($filters['category']) && (isset($filters['minPrice']) || isset($filters['maxPrice']))) {
            $f = $filters;
            unset($f['category']);
            $tiers[] = $f;
        }

        // Tier 4: if has category, try category only (drop price)
        if (!empty($filters['category']) && (isset($filters['minPrice']) || isset($filters['maxPrice']))) {
            $f = $filters;
            unset($f['minPrice'], $f['maxPrice']);
            $tiers[] = $f;
        }

        // Tier 5: if has price, try price only
        if (isset($filters['minPrice']) || isset($filters['maxPrice'])) {
            $f = $filters;
            unset($f['category'], $f['readingAge'], $f['language']);
            $tiers[] = $f;
        }

        // Deduplicate tiers
        $uniq = [];
        $seen = [];
        foreach ($tiers as $t) {
            ksort($t);
            $key = json_encode($t);
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $uniq[] = $t;
            }
        }
        return $uniq;
    }

    private function runFilterOnlyQuery($query, int $limit)
    {
        // When user asks by price/age/category only, sort for best picks
        return $query
            ->orderByRaw("CASE WHEN quantity > 0 THEN 0 ELSE 1 END")
            ->orderByDesc('discount')
            ->orderBy('price', 'asc')
            ->limit($limit)
            ->get(['id','title','author','category','price','discount','quantity','description','reading_age']);
    }

    private function runKeywordQuery($query, string $message, int $limit)
    {
        $kwRaw = trim($message);
        $kw = '%' . $this->escapeLike($kwRaw) . '%';

        // Purpose boost (soft ranking)
        $purpose = $this->inferPurposeFromMessage(mb_strtolower($message));
        $purposeLike = $purpose ? ('%' . $this->escapeLike($purpose) . '%') : null;

        $q = $query->where(function ($qq) use ($kw) {
            $qq->where('title', 'ILIKE', $kw)
               ->orWhere('author', 'ILIKE', $kw)
               ->orWhere('category', 'ILIKE', $kw)
               ->orWhere('description', 'ILIKE', $kw);
        });

        $q = $q->orderByRaw("
            (
                (CASE WHEN title ILIKE ? THEN 4 ELSE 0 END) +
                (CASE WHEN author ILIKE ? THEN 2 ELSE 0 END) +
                (CASE WHEN category ILIKE ? THEN 1 ELSE 0 END)
            ) DESC
        ", [$kw, $kw, $kw]);

        if ($purposeLike) {
            $q = $q->orderByRaw("(CASE WHEN description ILIKE ? THEN 1 ELSE 0 END) DESC", [$purposeLike]);
        }

        // Business sorting after relevance
        return $q
            ->orderByRaw("CASE WHEN quantity > 0 THEN 0 ELSE 1 END")
            ->orderByDesc('discount')
            ->orderBy('price', 'asc')
            ->limit($limit)
            ->get(['id','title','author','category','price','discount','quantity','description','reading_age']);
    }

    private function mapRows($rows): array
    {
        return $rows->map(function ($b) {
            $desc = trim(preg_replace('/\s+/', ' ', (string)$b->description));
            if (mb_strlen($desc) > 220) $desc = mb_substr($desc, 0, 220) . '...';

            $price = (float)$b->price;
            $discount = max((float)($b->discount ?? 0), 0);

            $finalPrice = $discount > 0 ? round($price * (1 - $discount / 100), 2) : round($price, 2);

            return [
                'id' => (int)$b->id,
                'title' => (string)$b->title,
                'author' => (string)$b->author,
                'category' => (string)$b->category,
                'price' => round($price, 2),
                'discount' => round($discount, 2),
                'finalPrice' => $finalPrice,
                'quantity' => (int)$b->quantity,
                'readingAge' => (int)($b->reading_age ?? 0),
                'purposeTags' => $this->inferPurposeTagsFromDescription($desc),
                'description' => $desc,
            ];
        })->toArray();
    }

    private function hasMeaningfulKeyword(string $lower, array $filters): bool
    {
        // remove numbers and currency signs
        $t = preg_replace('/[\$€£¥]/', ' ', $lower);
        $t = preg_replace('/\d+(?:\.\d+)?/', ' ', $t);

        // remove common filler words
        $stop = [
            'i','am','im','looking','for','a','an','the','good','book','books','recommend','recommendation',
            'that','which','cost','costs','price','priced','under','below','over','above','upper','between','and','to',
            'dollar','dollars','usd','cheap','affordable','best','any','some','me','please'
        ];

        $tokens = preg_split('/\s+/', trim($t));
        $tokens = array_filter($tokens, function ($w) use ($stop) {
            $w = trim($w);
            if ($w === '' || mb_strlen($w) < 4) return false;
            return !in_array($w, $stop, true);
        });

        // If user already gave a category, we don't need keyword
        if (!empty($filters['category']) && count($tokens) === 0) {
            return false;
        }

        return count($tokens) > 0;
    }

    private function inferPurposeFromMessage(string $lower): ?string
    {
        if (preg_match('/\b(study|exam|textbook|school|course|learn|reference)\b/', $lower)) return 'study';
        if (preg_match('/\b(self[- ]?help|self development|productivity|mindset|habits|growth)\b/', $lower)) return 'self-help';
        if (preg_match('/\b(life skills|communication|confidence|parenting|leadership|soft skills)\b/', $lower)) return 'life skills';
        if (preg_match('/\b(fun|entertain|relax|story|novel|fiction)\b/', $lower)) return 'novel';
        return null;
    }

    private function inferPurposeTagsFromDescription(string $desc): array
    {
        $d = mb_strtolower($desc);
        $tags = [];

        $study = ['textbook','curriculum','exam','practice','lesson','reference','guide','introduction to'];
        $self  = ['self-help','habits','mindset','productivity','success','motivation','personal growth'];
        $life  = ['communication','relationships','confidence','leadership','soft skills','life skills','parenting'];
        $ent   = ['novel','story','thriller','mystery','romance','fantasy','adventure','fiction'];

        foreach ($study as $k) if (str_contains($d, $k)) { $tags[] = 'study'; break; }
        foreach ($self  as $k) if (str_contains($d, $k)) { $tags[] = 'self-development'; break; }
        foreach ($life  as $k) if (str_contains($d, $k)) { $tags[] = 'life-skills'; break; }
        foreach ($ent   as $k) if (str_contains($d, $k)) { $tags[] = 'entertainment'; break; }

        return array_values(array_unique($tags));
    }

    private function escapeLike(string $s): string
    {
        // escape % _ for LIKE
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $s);
    }
}
