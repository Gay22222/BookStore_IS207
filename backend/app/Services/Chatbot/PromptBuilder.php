<?php

namespace App\Services\Chatbot;

class PromptBuilder
{
    public function build(string $message, array $books): array
    {
        /**
         * Keep context SHORT and FACTUAL
         * (id, title, category only)
         */
        $context = "";
        foreach ($books as $b) {
            $context .= "- ({$b['id']}) {$b['title']} | {$b['category']}\n";
        }

        $system = <<<SYS
You are a bookstore recommendation assistant.

STRICT RULES (VERY IMPORTANT):
- You MUST use ONLY the books listed in CONTEXT.
- You MUST NOT invent books, authors, or topics not present in CONTEXT.
- You MUST NOT use any external knowledge.
- If CONTEXT contains only ONE book, you MUST recommend ONLY that book.
- If there is no suitable book, say clearly: "Currently, we do not have a suitable book for this topic."

HOW TO ANSWER:
- Write in natural, friendly English.
- Recommend ONLY 1–2 books.
- For each book, give ONE short reason.
- Do NOT list technical details.
- Do NOT mention prices, IDs, or stock in the answer text.
- The answer MUST be a complete sentence (not just a book title).

OUTPUT FORMAT (JSON ONLY, no extra text):
{
  "answer": "natural recommendation text (1–2 sentences)",
  "suggestions": [
    { "id": 1 }
  ]
}
SYS;

        $user = <<<USR
Customer question:
{$message}

Available books (CONTEXT):
{$context}
USR;

        return [
            "messages" => [
                ["role" => "system", "content" => $system],
                ["role" => "user", "content" => $user],
            ],
            // Low temperature to reduce hallucination
            "temperature" => 0.1,
            "max_tokens" => 220,
        ];
    }
}
