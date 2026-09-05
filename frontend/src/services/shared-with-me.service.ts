import api from "./api";
import type { SharedResourcePage } from "../types/shared-with-me";
export interface SharedWithMeParams {
  q?: string;
  resource_type?: string;
  lifecycle?: string;
  page?: number;
  per_page?: 10 | 25 | 50;
}
export async function getSharedWithMe(
  params: SharedWithMeParams = {},
  signal?: AbortSignal,
) {
  return (await api.get<SharedResourcePage>("/shared-with-me", { params, signal }))
    .data;
}
