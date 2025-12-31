"use client";

import React, { useEffect } from "react";
import { FieldValues, useForm } from "react-hook-form";
import { Button, Spinner } from "flowbite-react";
import toast from "react-hot-toast";
import Input from "@/app/components/ui/Input";
import { useRouter } from "next/navigation";
import { changePassword } from "@/app/(user)/actions/userAction";

export default function ChangePasswordForm() {
  const {
    control,
    handleSubmit,
    setFocus,
    reset,
    watch,
    formState: { isSubmitting, isDirty, isValid },
  } = useForm({ mode: "onTouched" });

  const router = useRouter();

  const currentPassword = String(watch("currentPassword") ?? "");
  const newPassword = String(watch("newPassword") ?? "");

  useEffect(() => {
    setFocus("currentPassword");
  }, [setFocus]);

  const requiredMin8 = (label: string) => ({
    required: `${label} is required`,
    validate: (value: string) =>
      value?.trim().length >= 8 || `${label} must be at least 8 characters`,
  });

  async function onSubmit(data: FieldValues) {
    try {
      const current = String(data.currentPassword ?? "");
      const next = String(data.newPassword ?? "");
      const confirm = String(data.confirmNewPassword ?? "");

      const res = await changePassword(current, next, confirm);
      if ((res as any)?.error) throw (res as any).error;

      toast.success("Password updated successfully");
      reset();
      router.push("/account/change-password");
      router.refresh();
    } catch (error: any) {
      toast.error(error?.message || "Update password failed");
    }
  }

  return (
    <div className="mx-auto w-full max-w-3xl px-3 sm:px-4">
      <form
        className="
          grid grid-cols-1 gap-4 mt-3 min-w-0
          sm:grid-cols-2
          lg:grid-cols-2
          xl:grid-cols-3
        "
        onSubmit={handleSubmit(onSubmit)}
        noValidate
      >
        <div className="sm:col-span-2 xl:col-span-3">
          <Input
            name="currentPassword"
            label="Current Password"
            type="password"
            control={control}
            rules={requiredMin8("Current password")}
          />
        </div>

        <div className="sm:col-span-2 xl:col-span-1">
          <Input
            name="newPassword"
            label="New Password"
            type="password"
            control={control}
            rules={{
              ...requiredMin8("New password"),
              validate: (value: string) => {
                if (!value?.trim() || value.trim().length < 8)
                  return "New password must be at least 8 characters";
                if (value === currentPassword)
                  return "New password must be different from current password";
                return true;
              },
            }}
          />
        </div>

        <div className="sm:col-span-2 xl:col-span-1">
          <Input
            name="confirmNewPassword"
            label="Confirm New Password"
            type="password"
            control={control}
            rules={{
              ...requiredMin8("Confirm new password"),
              validate: (value: string) =>
                value === newPassword || "Passwords do not match",
            }}
          />
        </div>

        <div className="col-span-1 sm:col-span-2 xl:col-span-3 mt-2">
          <div className="flex flex-col-reverse sm:flex-row items-stretch sm:items-center justify-between gap-3">
            <Button
              color="alternative"
              onClick={() => router.push("/")}
              type="button"
              className="w-full sm:w-auto cursor-pointer"
            >
              Cancel
            </Button>

            <Button
              outline
              color="green"
              type="submit"
              disabled={!isValid || !isDirty || isSubmitting}
              className="w-full sm:w-auto flex items-center justify-center gap-2 cursor-pointer"
            >
              {isSubmitting && <Spinner size="sm" />}
              <span>{isSubmitting ? "Saving..." : "Submit"}</span>
            </Button>
          </div>
        </div>
      </form>
    </div>
  );
}
