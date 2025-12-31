"use server";

import { getAllCustomers } from "@/app/(user)/actions/adminApi";
import UserClientPage from "../manage-users/UsersClientPage";

export default async function ManageCustomersPage() {
  const res = await getAllCustomers();
  const users = "error" in (res as any) ? [] : (res as any);

  return (
    <section className="w-full max-w-7xl mx-auto space-y-4">
      <h1 className="text-2xl sm:text-3xl font-bold text-gray-900">
        Customers
      </h1>
      <UserClientPage initialUsers={users} variant="customers" />
    </section>
  );
}
