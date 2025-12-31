"use client";

import Link from "next/link";
import { Pencil, Trash2, ChevronLeft, ChevronRight } from "lucide-react";
import { Book } from "@/app/(user)/models/Book";
import { useCallback, useEffect, useMemo, useState } from "react";
import { deleteBook } from "@/app/(user)/actions/bookAction";
import toast from "react-hot-toast";

type Props = { books: Book[]; onDeleted?: (id: number) => void };
const PAGE_SIZE = 10;

export default function BookTable({ books, onDeleted }: Props) {
  const [page, setPage] = useState(1);

  // Reset page when data changes (e.g. after search)
  useEffect(() => {
    setPage(1);
  }, [books]);

  const totalPages = Math.max(1, Math.ceil(books.length / PAGE_SIZE));

  // Clamp page if books length changes
  useEffect(() => {
    if (page > totalPages) setPage(totalPages);
  }, [page, totalPages]);

  const pagedBooks = useMemo(() => {
    const start = (page - 1) * PAGE_SIZE;
    return books.slice(start, start + PAGE_SIZE);
  }, [books, page]);

  const handleDelete = useCallback(
    async (id: number) => {
      const confirm = window.confirm(
        "Are you sure you want to delete this book?"
      );
      if (!confirm) return;

      try {
        const res = await deleteBook(id);

        if (res && typeof res === "object" && "error" in (res as any)) {
          throw (res as any).error;
        }

        toast.success("Delete book succeeded");

        // ✅ update UI ngay
        onDeleted?.(id);

        // (không bắt buộc) nếu bạn vẫn muốn refresh server data:
        // router.refresh();
      } catch (error: any) {
        toast.error(
          error?.message ?? "Failed to delete book. Please try again."
        );
      }
    },
    [onDeleted]
  );

  const canPrev = page > 1;
  const canNext = page < totalPages;

  const goPrev = () => canPrev && setPage((p) => p - 1);
  const goNext = () => canNext && setPage((p) => p + 1);

  const pageNumbers = useMemo(() => {
    if (totalPages <= 7)
      return Array.from({ length: totalPages }, (_, i) => i + 1);

    const nums: (number | "...")[] = [];
    const left = Math.max(2, page - 1);
    const right = Math.min(totalPages - 1, page + 1);

    nums.push(1);
    if (left > 2) nums.push("...");

    for (let i = left; i <= right; i++) nums.push(i);

    if (right < totalPages - 1) nums.push("...");
    nums.push(totalPages);

    return nums;
  }, [page, totalPages]);

  const rows = useMemo(() => {
    return pagedBooks.map((b, idx) => {
      const globalIndex = (page - 1) * PAGE_SIZE + idx + 1;

      return (
        <tr key={b.id} className="odd:bg-white even:bg-gray-50">
          <td className="px-4 py-3 whitespace-nowrap text-sm text-gray-600">
            {globalIndex}
          </td>
          <td className="px-4 py-3 text-sm font-medium text-gray-900">
            {b.title}
          </td>
          <td className="px-4 py-3 text-sm text-gray-700">{b.author}</td>
          <td className="px-4 py-3 text-sm text-gray-700">{b.category}</td>
          <td className="px-4 py-3 text-sm text-gray-700">
            ${Number(b.price).toFixed(2)}
          </td>
          <td className="px-4 py-3 text-sm text-gray-700">
            {new Date(b.publicationDate).toLocaleDateString()}
          </td>

          <td className="px-4 py-3 text-sm">
            <div className="flex items-center gap-3">
              <Link
                href={`/admin/manage-books/update/${b.id}`}
                className="text-purple-600 hover:text-purple-800 cursor-pointer"
                title="Edit"
              >
                <Pencil className="w-4 h-4" />
              </Link>

              <button
                onClick={() => handleDelete(b.id)}
                className="text-rose-600 hover:text-rose-800 cursor-pointer"
                title="Delete"
              >
                <Trash2 className="w-4 h-4 cursor-pointer" />
              </button>
            </div>
          </td>
        </tr>
      );
    });
  }, [pagedBooks, page, handleDelete]);

  const from = books.length === 0 ? 0 : (page - 1) * PAGE_SIZE + 1;
  const to = Math.min(page * PAGE_SIZE, books.length);

  return (
    <div className="bg-white shadow rounded-lg overflow-hidden">
      {/* Empty state */}
      {books.length === 0 ? (
        <div className="p-6 text-gray-600 italic">
          There are no books in the database.
        </div>
      ) : (
        <>
          <div className="overflow-x-auto">
            <table className="min-w-full divide-y divide-gray-200">
              <thead className="bg-purple-100">
                <tr>
                  {[
                    "#",
                    "Title",
                    "Author",
                    "Category",
                    "Price",
                    "Publication Date",
                    "Actions",
                  ].map((h) => (
                    <th
                      key={h}
                      className="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider"
                    >
                      {h}
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-200">{rows}</tbody>
            </table>
          </div>

          {/* Pagination footer */}
          <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 px-4 py-3 border-t border-gray-200">
            <div className="text-sm text-gray-600">
              Showing <span className="font-medium">{from}</span>–{" "}
              <span className="font-medium">{to}</span> of{" "}
              <span className="font-medium">{books.length}</span>
            </div>

            <div className="flex items-center justify-between sm:justify-end gap-2">
              <button
                onClick={goPrev}
                disabled={!canPrev}
                className="
                  inline-flex items-center gap-1
                  rounded-lg border border-gray-200 bg-white
                  px-3 py-2 text-sm font-medium text-gray-700
                  hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed
                "
              >
                <ChevronLeft className="w-4 h-4" />
                Prev
              </button>

              <div className="flex items-center gap-1">
                {pageNumbers.map((n, idx) =>
                  n === "..." ? (
                    <span key={`dots-${idx}`} className="px-2 text-gray-500">
                      ...
                    </span>
                  ) : (
                    <button
                      key={n}
                      onClick={() => setPage(n)}
                      className={`
                        w-9 h-9 rounded-lg border text-sm font-medium
                        ${
                          n === page
                            ? "bg-purple-600 border-purple-600 text-white"
                            : "bg-white border-gray-200 text-gray-700 hover:bg-gray-50"
                        }
                      `}
                      aria-current={n === page ? "page" : undefined}
                    >
                      {n}
                    </button>
                  )
                )}
              </div>

              <button
                onClick={goNext}
                disabled={!canNext}
                className="
                  inline-flex items-center gap-1
                  rounded-lg border border-gray-200 bg-white
                  px-3 py-2 text-sm font-medium text-gray-700
                  hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed
                "
              >
                Next
                <ChevronRight className="w-4 h-4" />
              </button>
            </div>
          </div>
        </>
      )}
    </div>
  );
}
