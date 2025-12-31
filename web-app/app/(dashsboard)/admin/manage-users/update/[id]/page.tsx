import UserForm from "../../UserForm";
import { getAllUsers } from "@/app/(user)/actions/adminApi";

export default async function Page({
  params,
}: {
  params: Promise<{ id: string }>;
}) {
  const { id } = await params;
  const userId = Number(id);

  const res = await getAllUsers();

  const users = "error" in (res as any) ? [] : (res as any);
  const user = users.find((u: any) => Number(u.id) === userId);

  return (
    <section className="w-full max-w-7xl mx-auto space-y-4">
      <h1 className="text-2xl sm:text-3xl font-bold text-gray-900">
        Update User
      </h1>
      <UserForm user={user} />
    </section>
  );
}
