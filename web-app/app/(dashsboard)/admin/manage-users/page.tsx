"use server";

import Link from "next/link";
import { getAllUsers } from "@/app/(user)/actions/adminApi";
import UserClientPage from "./UsersClientPage";

export default async function ManageUsersPage() {
  const res = await getAllUsers();

  const users = "error" in (res as any) ? [] : (res as any);

  return (
    <section className="w-full max-w-7xl mx-auto">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between mb-6">
        <h1 className="text-2xl sm:text-3xl font-bold text-gray-900">
          All Users
        </h1>

        <Link
          href="/admin/manage-users/new"
          className="w-full sm:w-auto inline-flex items-center justify-center gap-2 bg-purple-600 hover:bg-purple-700 text-white text-sm font-medium py-2.5 px-4 rounded-lg shadow transition-colors"
        >
          + Add User
        </Link>
      </div>

      <UserClientPage initialUsers={users} variant="users" />
    </section>
  );
}
