"use client";

import React, { useEffect } from "react";
import { FieldValues, useForm } from "react-hook-form";
import { Button, Spinner } from "flowbite-react";
import toast from "react-hot-toast";
import Input from "@/app/components/ui/Input";
import { usePathname, useRouter } from "next/navigation";
import { addBook, AdminBook, updateBook } from "@/app/(user)/actions/adminApi";

type Props = {
  book?: AdminBook;
};

export default function BookForm({ book }: Props) {
  const {
    control,
    handleSubmit,
    setFocus,
    reset,
    formState: { isSubmitting, isDirty, isValid },
  } = useForm({
    mode: "onTouched",
  });

  const pathName = usePathname();
  const router = useRouter();

  useEffect(() => {
    if (book) {
      const {
        title,
        author,
        description,
        category,
        price,
        publisher,
        publicationDate,
        language,
        readingAge,
        pages,
        dimension,
        quantity,
        discount,
        imageUrl,
      } = book;

      reset({
        title,
        author,
        description,
        category,
        price,
        publisher,
        publicationDate,
        language,
        readingAge,
        pages,
        dimension,
        quantity,
        discount,
        imageUrl,
      });
    }

    setFocus("title");
  }, [setFocus, book, reset]);

  async function onSubmit(data: FieldValues) {
    try {
      let res;

      if (pathName === "/admin/manage-books/new") {
        res = await addBook(data as any);
      } else {
        const id = book?.id ?? book?.bookId;
        if (!id) throw { status: "400", message: "Missing book id" };
        res = await updateBook(data as any, id);
      }

      if ((res as any)?.error) throw (res as any).error;

      router.push(`/admin/manage-books`);
    } catch (error: any) {
      toast.error(error.status + " " + error.message);
    }
  }

  return (
    <div className="w-full max-w-6xl mx-auto">
      <div className="rounded-2xl border border-gray-200 bg-white/80 backdrop-blur shadow-sm p-4 sm:p-6 lg:p-8">
        <form onSubmit={handleSubmit(onSubmit)} className="space-y-6">
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-4 sm:gap-5">
            <div className="flex flex-col gap-4">
              <Input
                name="title"
                label="Title"
                control={control}
                rules={{ required: "Title is required" }}
              />
              <Input
                name="author"
                label="Author"
                control={control}
                rules={{ required: "Author is required" }}
              />
              <Input
                name="category"
                label="Category"
                control={control}
                rules={{ required: "Category is required" }}
              />
              <Input
                name="price"
                label="Price"
                type="number"
                control={control}
                rules={{ required: "Price is required", min: 0 }}
              />
              <Input
                name="publicationDate"
                label="Publication Date"
                type="date"
                control={control}
                rules={{ required: "Publication date is required" }}
              />
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <Input
                  name="readingAge"
                  label="Reading Age"
                  type="number"
                  control={control}
                  rules={{ required: "Reading age is required", min: 0 }}
                />
                <Input
                  name="quantity"
                  label="Quantity"
                  type="number"
                  control={control}
                  rules={{ required: "Quantity is required", min: 0 }}
                />
              </div>
            </div>

            <div className="flex flex-col gap-4">
              <Input
                name="description"
                label="Description"
                control={control}
                rules={{ required: "Description is required" }}
              />
              <Input
                name="publisher"
                label="Publisher"
                control={control}
                rules={{ required: "Publisher is required" }}
              />
              <Input
                name="language"
                label="Language"
                control={control}
                rules={{ required: "Language is required" }}
              />
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <Input
                  name="pages"
                  label="Pages"
                  type="number"
                  control={control}
                  rules={{ required: "Pages is required", min: 1 }}
                />
                <Input
                  name="dimension"
                  label="Dimension"
                  control={control}
                  rules={{ required: "Dimension is required" }}
                />
              </div>
              <Input
                name="discount"
                label="Discount (%)"
                type="number"
                control={control}
                rules={{ required: "Discount is required", min: 0 }}
              />
              <Input
                name="imageUrl"
                label="Image URL"
                control={control}
                rules={{}}
              />
            </div>
          </div>

          <div className="flex flex-col-reverse sm:flex-row sm:justify-between gap-3 pt-2">
            <Button
              color="alternative"
              onClick={() => router.push("/admin/manage-books")}
              type="button"
              className="w-full sm:w-auto"
            >
              Cancel
            </Button>

            <Button
              outline
              color="green"
              type="submit"
              disabled={!isValid || !isDirty}
              className="w-full sm:w-auto"
            >
              {isSubmitting && <Spinner size="sm" className="mr-2" />}
              Submit
            </Button>
          </div>
        </form>
      </div>
    </div>
  );
}
