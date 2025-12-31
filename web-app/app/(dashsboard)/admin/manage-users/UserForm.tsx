"use client";

import React, { useEffect, useMemo } from "react";
import { FieldValues, useForm, useWatch } from "react-hook-form";
import { Button, Spinner } from "flowbite-react";
import toast from "react-hot-toast";
import Input from "@/app/components/ui/Input";
import { usePathname, useRouter } from "next/navigation";
import type { AdminUser } from "@/app/(user)/actions/adminApi";
import { createUser, updateUser } from "@/app/(user)/actions/adminApi";

type Props = {
  user?: AdminUser;
};

const ROLE_OPTIONS = ["ROLE_USER", "ROLE_EMPLOYEE", "ROLE_ADMIN"] as const;

function normalizeRole(role: string) {
  const r = String(role ?? "")
    .trim()
    .toUpperCase();

  const aliases: Record<string, string> = {
    ADMIN: "ROLE_ADMIN",
    ROLE_ADMIN: "ROLE_ADMIN",

    EMPLOYEE: "ROLE_EMPLOYEE",
    EMPLOYEES: "ROLE_EMPLOYEE",
    ROLE_EMPLOYEE: "ROLE_EMPLOYEE",
    ROLE_EMPLOYEES: "ROLE_EMPLOYEE",

    USER: "ROLE_USER",
    ROLE_USER: "ROLE_USER",
  };

  return aliases[r] ?? r;
}

function normalizeRoles(input: unknown): string[] {
  const arr = Array.isArray(input) ? input : [];
  const normalized = arr.map((x) => normalizeRole(String(x))).filter(Boolean);

  // chỉ giữ các role bạn cho phép chọn
  const allowed = new Set(ROLE_OPTIONS);
  const filtered = normalized.filter((r) => allowed.has(r as any));

  // unique
  return Array.from(new Set(filtered));
}

export default function UserForm({ user }: Props) {
  const pathName = usePathname();
  const router = useRouter();

  const isCreate = useMemo(
    () => pathName === "/admin/manage-users/new",
    [pathName]
  );

  const {
    control,
    handleSubmit,
    setFocus,
    reset,
    setValue,
    formState: { isSubmitting, isDirty, isValid },
  } = useForm<FieldValues>({
    mode: "onTouched",
    defaultValues: {
      userName: "",
      email: "",
      password: "",
      roles: ["ROLE_USER"],
    },
  });

  // ✅ useWatch để state roles luôn sync chuẩn khi reset/setValue
  const roles =
    (useWatch({
      control,
      name: "roles",
      defaultValue: ["ROLE_USER"],
    }) as string[]) ?? [];

  useEffect(() => {
    if (user) {
      const initialRoles =
        normalizeRoles(user.roles).length > 0
          ? normalizeRoles(user.roles)
          : ["ROLE_USER"];

      reset({
        userName: user.userName,
        email: user.email,
        password: "",
        roles: initialRoles,
      });
    } else {
      reset({
        userName: "",
        email: "",
        password: "",
        roles: ["ROLE_USER"],
      });
    }

    setFocus("userName");
  }, [user, reset, setFocus]);

  const toggleRole = (r: string) => {
    const role = normalizeRole(r);
    const current = normalizeRoles(roles);

    const next = current.includes(role)
      ? current.filter((x) => x !== role)
      : [...current, role];

    setValue("roles", next, { shouldDirty: true, shouldValidate: true });
  };

  async function onSubmit(data: FieldValues) {
    try {
      const payloadRoles = normalizeRoles(data.roles);

      if (isCreate) {
        const payload = {
          userName: String(data.userName ?? ""),
          email: String(data.email ?? ""),
          password: String(data.password ?? ""),
          roles: payloadRoles,
        };

        const res = await createUser(payload);
        if ("error" in (res as any)) throw (res as any).error;
        toast.success("Create user succeeded");
      } else {
        if (!user?.id)
          throw { status: "Code 400:\n", message: "Missing user id" };

        const payload = {
          updatedUserId: user.id,
          userName: String(data.userName ?? ""),
          email: String(data.email ?? ""),
          roles: payloadRoles,
        };

        const res = await updateUser(payload);
        if ("error" in (res as any)) throw (res as any).error;
        toast.success("Update user succeeded");
      }

      router.push("/admin/manage-users");
      router.refresh();
    } catch (error: any) {
      toast.error(
        (error?.status ?? "") + " " + (error?.message ?? "Something went wrong")
      );
    }
  }

  return (
    <div className="w-full max-w-5xl mx-auto">
      <div className="rounded-2xl border border-gray-200 bg-white/80 backdrop-blur shadow-sm p-4 sm:p-6 lg:p-8">
        <form onSubmit={handleSubmit(onSubmit)} className="space-y-6">
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-4 sm:gap-5">
            <Input
              name="userName"
              label="User Name"
              control={control}
              rules={{
                required: "User name is required",
                minLength: 2,
                maxLength: 20,
              }}
            />

            <Input
              name="email"
              label="Email"
              control={control}
              rules={{ required: "Email is required" }}
            />

            {isCreate && (
              <Input
                name="password"
                label="Password"
                type="password"
                control={control}
                rules={{ required: "Password is required", minLength: 6 }}
              />
            )}

            {/* Roles */}
            <div className="lg:col-span-2">
              <div className="text-sm font-semibold text-gray-800 mb-2">
                Roles
              </div>
              <div className="flex flex-wrap gap-2">
                {ROLE_OPTIONS.map((r) => {
                  const checked = normalizeRoles(roles).includes(r);
                  return (
                    <button
                      type="button"
                      key={r}
                      onClick={() => toggleRole(r)}
                      className={`px-3 py-2 rounded-lg border text-sm font-medium transition ${
                        checked
                          ? "bg-purple-600 border-purple-600 text-white"
                          : "bg-white border-gray-200 text-gray-700 hover:bg-gray-50"
                      }`}
                    >
                      {r}
                    </button>
                  );
                })}
              </div>

              <div className="text-xs text-gray-500 mt-2">
                Current selected:{" "}
                <span className="font-medium text-gray-700">
                  {normalizeRoles(roles).join(", ") || "None"}
                </span>
              </div>
            </div>
          </div>

          <div className="flex flex-col-reverse sm:flex-row sm:justify-between gap-3 pt-2">
            <Button
              color="alternative"
              type="button"
              className="w-full sm:w-auto"
              onClick={() => router.push("/admin/manage-users")}
            >
              Cancel
            </Button>

            <Button
              outline
              color="green"
              type="submit"
              className="w-full sm:w-auto"
              disabled={!isValid || !isDirty}
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
