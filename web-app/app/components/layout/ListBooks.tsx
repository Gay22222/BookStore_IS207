"use client";

import React, { useMemo, useState } from "react";
import { ChevronLeft, ChevronRight } from "lucide-react";
import BookImage from "../ui/BookImage";
import Link from "next/link";
import { Book } from "@/app/(user)/models/Book";
import AddOneToCartSection from "../ui/AddOneToCartSection";

interface Props {
  books: Book[];
  title: string;
}

const ITEMS_PER_PAGE = 4;

const fmtMoney = (v: number, currency = "USD", locale = "en-US") =>
  new Intl.NumberFormat(locale, { style: "currency", currency }).format(v);

export default function ListBooks({ books, title }: Props) {
  const [currentPage, setCurrentPage] = useState(1);

  const totalPages = Math.max(1, Math.ceil(books.length / ITEMS_PER_PAGE));

  const currentBooks = useMemo(() => {
    const startIndex = (currentPage - 1) * ITEMS_PER_PAGE;
    const endIndex = startIndex + ITEMS_PER_PAGE;
    return books.slice(startIndex, endIndex);
  }, [books, currentPage]);

  const goToPreviousPage = () =>
    currentPage > 1 && setCurrentPage((p) => p - 1);
  const goToNextPage = () =>
    currentPage < totalPages && setCurrentPage((p) => p + 1);
  const goToPage = (n: number) => setCurrentPage(n);

  return (
    <div className="mx-auto w-[92%] md:w-4/5 mt-10">
      <h2 className="text-2xl sm:text-3xl font-bold mb-6">{title}</h2>

      <div className="relative">
        <div className="grid gap-5 sm:gap-7 grid-cols-2 sm:grid-cols-3 lg:grid-cols-4">
          {currentBooks.map((book) => {
            const price = Number(book.price) || 0;
            const discount = Math.max(Number(book.discount) || 0, 0);
            const hasDiscount = discount > 0;
            const finalPrice = hasDiscount
              ? price * (1 - discount / 100)
              : price;
            const saved = Math.max(price - finalPrice, 0);

            return (
              <div
                key={book.id}
                className="
                  group rounded-xl border bg-white
                  shadow-sm hover:shadow-md transition
                  p-4 sm:p-5
                  flex flex-col h-full
                "
              >
                {/* Image */}
                <div className="relative mb-4 aspect-[3/4] overflow-hidden rounded-lg bg-gray-50">
                  <Link href={`/books/${book.id}`} className="block h-full">
                    <BookImage
                      title={book.title}
                      imageUrl={book.imageUrl}
                      fit="contain"
                    />
                  </Link>
                </div>

                {/* Title (max 2 lines) */}
                <h3
                  className="
                    text-base sm:text-lg font-semibold leading-snug text-gray-900
                    overflow-hidden [display:-webkit-box] [-webkit-line-clamp:2] [-webkit-box-orient:vertical]
                    min-h-[3rem] sm:min-h-[3.5rem]
                    mb-2
                  "
                >
                  {book.title}
                </h3>

                {/* Author */}
                <p className="text-sm sm:text-base text-gray-600 overflow-hidden text-ellipsis whitespace-nowrap mb-3">
                  {book.author}
                </p>

                {/* Price */}
                <div className="mb-4">
                  <div className="flex items-center flex-wrap gap-x-2 gap-y-2">
                    <span className="text-xl sm:text-2xl font-bold text-gray-900">
                      {fmtMoney(finalPrice)}
                    </span>

                    {hasDiscount && (
                      <span className="text-base sm:text-lg text-gray-500 line-through">
                        {fmtMoney(price)}
                      </span>
                    )}

                    {hasDiscount && (
                      <span className="inline-flex items-center rounded-full bg-rose-50 text-rose-600 px-2.5 py-1 text-sm font-semibold">
                        -{Math.round(discount)}%
                      </span>
                    )}
                  </div>

                  {hasDiscount && (
                    <div className="text-sm text-gray-500 mt-1">
                      Save {fmtMoney(saved)}
                    </div>
                  )}
                </div>

                {/* Button pinned to bottom */}
                <div className="mt-auto">
                  <AddOneToCartSection bookId={String(book.id)} />
                </div>
              </div>
            );
          })}
        </div>

        {/* Prev */}
        <button
          onClick={goToPreviousPage}
          className="
            hidden md:flex
            absolute top-1/2 -translate-y-1/2 -left-16
            rounded-full border border-gray-300 bg-white
            p-2 shadow-sm hover:shadow transition
            disabled:opacity-40 disabled:cursor-not-allowed
          "
          aria-label="Trang trước"
          disabled={currentPage === 1}
        >
          <ChevronLeft className="h-6 w-6" />
        </button>

        {/* Next */}
        <button
          onClick={goToNextPage}
          className="
            hidden md:flex
            absolute top-1/2 -translate-y-1/2 -right-16
            rounded-full border border-gray-300 bg-white
            p-2 shadow-sm hover:shadow transition
            disabled:opacity-40 disabled:cursor-not-allowed
          "
          aria-label="Trang sau"
          disabled={currentPage === totalPages}
        >
          <ChevronRight className="h-6 w-6" />
        </button>
      </div>

      {/* Pagination bullets */}
      <div className="flex justify-center items-center mt-7 gap-2.5">
        {Array.from({ length: totalPages }, (_, i) => i + 1).map((page) => (
          <button
            key={page}
            onClick={() => goToPage(page)}
            className={`
              w-3 h-3 sm:w-3.5 sm:h-3.5 rounded-full border-2
              ${
                page === currentPage
                  ? "bg-purple-600 border-purple-600"
                  : "bg-white border-purple-600"
              }
            `}
            aria-label={`Trang ${page}`}
            aria-current={page === currentPage ? "page" : undefined}
          />
        ))}
      </div>
    </div>
  );
}
