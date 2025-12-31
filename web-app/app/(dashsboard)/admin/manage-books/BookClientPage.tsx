"use client";

import { useEffect, useState } from "react";
import BookTable from "@/app/components/ui/BookTable";
import { Book } from "@/app/(user)/models/Book";

export default function BookClientPage({
  initialBooks,
}: {
  initialBooks: Book[];
}) {
  const [books, setBooks] = useState<Book[]>(initialBooks);

  useEffect(() => {
    setBooks(initialBooks);
  }, [initialBooks]);

  const handleDeleted = (deletedId: number) => {
    setBooks((prev) => prev.filter((b) => b.id !== deletedId));
  };

  return <BookTable books={books} onDeleted={handleDeleted} />;
}
