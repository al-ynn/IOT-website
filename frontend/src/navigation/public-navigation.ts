export const publicNavigation = [
  { label: "Platform", to: "/" },
  { label: "Features", to: "/features" },
  { label: "Solutions", to: "/solutions" },
  { label: "Developers", to: "/developers" },
  { label: "Enterprise", to: "/enterprise" },
  { label: "Pricing", to: "/pricing" },
  { label: "Docs", to: "/docs" },
] as const;

export const publicFooterNavigation = [
  ...publicNavigation,
  { label: "Contact", to: "/contact" },
  { label: "About", to: "/about" },
] as const;
