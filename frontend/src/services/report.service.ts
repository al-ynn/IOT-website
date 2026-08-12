/** Laravel currently exposes no report, export, generation, or download endpoints. */
export const reportCapabilities = {
  list: false,
  create: false,
  view: false,
  remove: false,
  generate: false,
  exports: false,
  download: false,
} as const;
