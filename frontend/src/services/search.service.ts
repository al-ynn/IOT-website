import api from "./api";
import type { SearchResponse } from "../types/search";

export async function searchResources(q: string, page = 1, signal?: AbortSignal): Promise<SearchResponse> {
  const { data } = await api.get<SearchResponse>("/search", { params: { q, page, per_page: 25 }, signal });
  return data;
}
