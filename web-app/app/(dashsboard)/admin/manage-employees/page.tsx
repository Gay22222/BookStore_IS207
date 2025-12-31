"use server";

import { getAllEmployees } from "@/app/(user)/actions/adminApi";
import UserClientPage from "../manage-users/UsersClientPage";

export default async function ManageEmployeesPage() {
  const res = await getAllEmployees();
  const users = "error" in (res as any) ? [] : (res as any);

  return (
    <section className="w-full max-w-7xl mx-auto space-y-4">
      <h1 className="text-2xl sm:text-3xl font-bold text-gray-900">
        Employees
      </h1>
      <UserClientPage initialUsers={users} variant="employees" />
    </section>
  );
}
