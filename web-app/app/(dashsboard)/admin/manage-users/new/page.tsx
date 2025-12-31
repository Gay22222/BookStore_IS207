import UserForm from "../UserForm";

export default function Page() {
  return (
    <section className="w-full max-w-7xl mx-auto space-y-4">
      <h1 className="text-2xl sm:text-3xl font-bold text-gray-900">
        Add New User
      </h1>
      <UserForm />
    </section>
  );
}
