"use client";

import { useState } from "react";
import SearchBar from "@/app/components/ui/SearchBarReusable";
import UserTable from "@/app/components/ui/UserTable";
import type { AdminUser } from "@/app/(user)/actions/adminApi";
import {
  searchUsers,
  searchCustomers,
  searchEmployees,
} from "@/app/(user)/actions/adminApi";

type Variant = "users" | "customers" | "employees";

export default function UserClientPage({
  initialUsers,
  variant = "users",
}: {
  initialUsers: AdminUser[];
  variant?: Variant;
}) {
  const [users, setUsers] = useState(initialUsers);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const handleSearch = async (term: string) => {
    setLoading(true);
    setError(null);

    try {
      const fn =
        variant === "customers"
          ? searchCustomers
          : variant === "employees"
          ? searchEmployees
          : searchUsers;

      const res = await fn(term);

      if ("error" in res) {
        setError(res.error.message);
        setUsers([]);
      } else {
        setUsers(res);
      }
    } catch (err) {
      setError("Something went wrong: " + String(err));
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="space-y-4">
      <div className="flex flex-col sm:flex-row gap-3 sm:items-center">
        <div className="flex-1 min-w-0">
          <SearchBar onSearch={handleSearch} placeholder="Search users..." />
        </div>

        <div className="text-sm text-gray-600">
          {loading ? "Loading..." : `${users.length} results`}
        </div>
      </div>

      {error && (
        <div className="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
          {error}
        </div>
      )}

      <UserTable users={users} />
    </div>
  );
}
