import { PropsWithChildren } from "react";
import { getCurrentUser } from "../(user)/actions/getCurrentUser";
import AdminShell from "../components/layout/AdminShell";

export default async function LayoutAdminPage({ children }: PropsWithChildren) {
  const user = await getCurrentUser();

  return <AdminShell user={user}>{children}</AdminShell>;
}
