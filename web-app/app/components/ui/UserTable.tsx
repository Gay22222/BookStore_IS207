"use client";

import Link from "next/link";
import { Pencil, Trash2, ChevronLeft, ChevronRight } from "lucide-react";
import { useCallback, useEffect, useMemo, useState } from "react";
import toast from "react-hot-toast";
import { useRouter } from "next/navigation";
import type { AdminUser } from "@/app/(user)/actions/adminApi";
import { deleteUser } from "@/app/(user)/actions/adminApi";

type Props = { users: AdminUser[] };

const PAGE_SIZE = 10;

export default function UserTable({ users }: Props) {
  const router = useRouter();
  const [page, setPage] = useState(1);

  useEffect(() => setPage(1), [users]);

  const totalPages = Math.max(1, Math.ceil(users.length / PAGE_SIZE));

  useEffect(() => {
    if (page > totalPages) setPage(totalPages);
  }, [page, totalPages]);

  const pagedUsers = useMemo(() => {
    const start = (page - 1) * PAGE_SIZE;
    return users.slice(start, start + PAGE_SIZE);
  }, [users, page]);

  const handleDelete = useCallback(
    async (id: number) => {
      const ok = window.confirm("Are you sure you want to delete this user?");
      if (!ok) return;

      const res = await deleteUser(id);
      if ((res as any)?.error) {
        toast.error((res as any).error.message);
        return;
      }

      toast.success("Delete user succeeded");
      router.refresh();
    },
    [router]
  );

  const canPrev = page > 1;
  const canNext = page < totalPages;

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
    return pagedUsers.map((u, idx) => {
      const globalIndex = (page - 1) * PAGE_SIZE + idx + 1;

      return (
        <tr key={u.id} className="odd:bg-white even:bg-gray-50">
          <td className="px-4 py-3 whitespace-nowrap text-sm text-gray-600">
            {globalIndex}
          </td>

          <td className="px-4 py-3 text-sm font-medium text-gray-900">
            {u.userName}
          </td>

          <td className="px-4 py-3 text-sm text-gray-700">{u.email}</td>

          <td className="px-4 py-3 text-sm text-gray-700">
            {u.roles?.length ? u.roles.join(", ") : "-"}
          </td>

          <td className="px-4 py-3 text-sm">
            <div className="flex items-center gap-3">
              <Link
                href={`/admin/manage-users/update/${u.id}`}
                className="text-purple-600 hover:text-purple-800"
                title="Edit"
              >
                <Pencil className="w-4 h-4" />
              </Link>

              <button
                onClick={() => handleDelete(u.id)}
                className="text-rose-600 hover:text-rose-800"
                title="Delete"
              >
                <Trash2 className="w-4 h-4" />
              </button>
            </div>
          </td>
        </tr>
      );
    });
  }, [pagedUsers, page, handleDelete]);

  const from = users.length === 0 ? 0 : (page - 1) * PAGE_SIZE + 1;
  const to = Math.min(page * PAGE_SIZE, users.length);

  return (
    <div className="bg-white shadow rounded-lg overflow-hidden">
      {users.length === 0 ? (
        <div className="p-6 text-gray-600 italic">There are no users.</div>
      ) : (
        <>
          <div className="overflow-x-auto">
            <table className="min-w-[900px] w-full divide-y divide-gray-200">
              <thead className="bg-purple-100">
                <tr>
                  {["#", "UserName", "Email", "Roles", "Actions"].map((h) => (
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

          <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 px-4 py-3 border-t border-gray-200">
            <div className="text-sm text-gray-600">
              Showing <span className="font-medium">{from}</span>–{" "}
              <span className="font-medium">{to}</span> of{" "}
              <span className="font-medium">{users.length}</span>
            </div>

            <div className="flex items-center justify-between sm:justify-end gap-2">
              <button
                onClick={() => canPrev && setPage((p) => p - 1)}
                disabled={!canPrev}
                className="inline-flex items-center gap-1 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-40"
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
                      className={`w-9 h-9 rounded-lg border text-sm font-medium ${
                        n === page
                          ? "bg-purple-600 border-purple-600 text-white"
                          : "bg-white border-gray-200 text-gray-700 hover:bg-gray-50"
                      }`}
                    >
                      {n}
                    </button>
                  )
                )}
              </div>

              <button
                onClick={() => canNext && setPage((p) => p + 1)}
                disabled={!canNext}
                className="inline-flex items-center gap-1 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-40"
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
