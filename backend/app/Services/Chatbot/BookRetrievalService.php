<?php

namespace App\Services\Chatbot;

use App\Models\Book;

class BookRetrievalService
{
    public function search(string $message, array $filters, int $limit = 8): array
    {
        $baseQuery = Book::query();

        // ===== Optional filters =====
        if (!empty($filters['category'])) {
            $baseQuery->where('category', $filters['category']);
        }

        if (!empty($filters['language'])) {
            $baseQuery->where('language', $filters['language']);
        }

        if (isset($filters['minPrice'])) {
            $baseQuery->where('price', '>=', (float)$filters['minPrice']);
        }

        if (isset($filters['maxPrice'])) {
            $baseQuery->where('price', '<=', (float)$filters['maxPrice']);
        }

        // ===== Keyword search (ILIKE for Postgres) =====
        $kwRaw = trim($message);
        $kw = '%' . $this->escapeLike($kwRaw) . '%';

        $q1 = (clone $baseQuery)->where(function ($qq) use ($kw) {
            $qq->where('title', 'ILIKE', $kw)
               ->orWhere('author', 'ILIKE', $kw)
               ->orWhere('category', 'ILIKE', $kw)
               ->orWhere('description', 'ILIKE', $kw);
        });

        $rows = $q1->select(['id','title','author','category','price','quantity','description'])
                   // heuristic score: title > author > category > description
                   ->orderByRaw("
                        (CASE WHEN title ILIKE ? THEN 4 ELSE 0 END) +
                        (CASE WHEN author ILIKE ? THEN 2 ELSE 0 END) +
                        (CASE WHEN category ILIKE ? THEN 1 ELSE 0 END)
                        DESC
                    ", [$kw, $kw, $kw])
                   ->limit($limit)
                   ->get();

        // ===== Fallback: nếu không match keyword, vẫn trả sách theo filter / còn hàng =====
        // Fallback CHỈ khi KHÔNG có sách nào cùng category
        if ($rows->count() === 0 && !empty($filters['category'])) {
            $q2 = Book::query()
                ->where('category', $filters['category'])
                ->orderByRaw("CASE WHEN quantity > 0 THEN 0 ELSE 1 END")
                ->limit($limit);

            $rows = $q2->select(['id','title','author','category','price','quantity','description'])->get();
        }


        return $rows->map(function ($b) {
            $desc = trim(preg_replace('/\s+/', ' ', (string)$b->description));
            if (mb_strlen($desc) > 200) $desc = mb_substr($desc, 0, 200) . '...';

            return [
                'id' => (int)$b->id,
                'title' => (string)$b->title,
                'author' => (string)$b->author,
                'category' => (string)$b->category,
                'price' => (float)$b->price,
                'quantity' => (int)$b->quantity,
                'description' => $desc,
            ];
        })->toArray();
    }

    private function escapeLike(string $s): string
    {
        // escape % _ for LIKE
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $s);
    }
}
