"use server";

import { getAllBooks } from "@/app/(user)/actions/bookAction";
import BookClientPage from "./BookClientPage";
import Link from "next/link";

export default async function BooksPage() {
  const res = await getAllBooks();
  const books = "error" in res ? [] : res.data;

  return (
    <section className="w-full max-w-7xl mx-auto">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between mb-6">
        <h1 className="text-2xl sm:text-3xl font-bold text-gray-900">
          All Books
        </h1>

        <Link
          href="/admin/manage-books/new"
          className="
            inline-flex items-center justify-center gap-2
            bg-purple-600 hover:bg-purple-700
            text-white text-sm font-medium
            py-2.5 px-4 rounded-lg shadow
            transition-colors
            w-full sm:w-auto
          "
        >
          + Add Book
        </Link>
      </div>

      <BookClientPage initialBooks={books} />
    </section>
  );
}
