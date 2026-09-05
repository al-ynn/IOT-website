import { useEffect, useState } from "react";
import { getTemplateReviewHistory } from "../../services/device-template.service";
import type { ReviewCycle } from "../../types/review-history";
import { ReviewHistoryTimeline } from "./ReviewHistoryTimeline";

export function TemplateReviewHistory({ templateId }: { templateId: string }) {
  const [cycles, setCycles] = useState<ReviewCycle[]>([]);
  const [error, setError] = useState("");

  useEffect(() => {
    let active = true;
    getTemplateReviewHistory(templateId)
      .then((page) => {
        if (active) setCycles(page.data);
      })
      .catch(() => {
        if (active) setError("Review history could not be loaded.");
      });
    return () => {
      active = false;
    };
  }, [templateId]);

  if (error) return <p role="alert">{error}</p>;
  return <ReviewHistoryTimeline cycles={cycles} />;
}
