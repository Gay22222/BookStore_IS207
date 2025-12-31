"use client";

import { useState } from "react";
import UserTable from "@/app/components/ui/UserTable";
import type { UserResponseForAdmin } from "@/app/(user)/models/UserResponseForAdmin";
import { searchCustomers } from "@/app/(user)/actions/userAction";
import SearchBar from "@/app/components/ui/SearchBarReusable";
import type { AdminUser } from "@/app/(user)/actions/adminApi";

function roleToString(role: any): string {
  if (typeof role === "string") return role;
  if (!role) return "";
  return (
    role.name ?? role.roleName ?? role.code ?? role.slug ?? role.title ?? ""
  );
}

function toAdminUser(u: UserResponseForAdmin): AdminUser {
  return {
    id: u.id,
    userName: u.userName,
    email: u.email,
    roles: (u.roles ?? []).map(roleToString).filter(Boolean),
  };
}

export default function CustomersClientPage({
  initialUsers,
}: {
  initialUsers: UserResponseForAdmin[];
}) {
  const [users, setUsers] = useState<AdminUser[]>(
    initialUsers.map(toAdminUser)
  );
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const handleSearch = async (term: string) => {
    setLoading(true);
    setError(null);

    try {
      const res = await searchCustomers(term);

      if ("error" in (res as any)) {
        setError((res as any).error.message);
        setUsers([]);
      } else {
        setUsers((res as UserResponseForAdmin[]).map(toAdminUser));
      }
    } catch (err) {
      setError("Something went wrong." + String(err));
    } finally {
      setLoading(false);
    }
  };

  return (
    <div>
      <div className="mb-4">
        <SearchBar onSearch={handleSearch} placeholder="Search users..." />
      </div>

      {loading && <p>Loading...</p>}
      {error && <p className="text-red-500">{error}</p>}
      <UserTable users={users} />
    </div>
  );
}
