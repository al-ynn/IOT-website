import { useEffect, useRef, useState } from "react";
import type { PointerEvent as ReactPointerEvent } from "react";
import type { Device } from "../../../types/device";
import api from "../../../services/api";

interface ImageMapWidgetProps {
  devices: Device[];
  backgroundImageUrl?: string;
  editable?: boolean;
  onMarkersChange?: (markers: NonNullable<ImageMapWidgetProps["markers"]>) => void;
  markers?: Array<{
    id: string;
    x: number;
    y: number;
    deviceId?: string;
    label?: string;
  }>;
}

interface MarkerWithData {
  x: number;
  y: number;
  device?: Device;
  label?: string;
}

export default function ImageMapWidget({
  devices,
  backgroundImageUrl,
  markers = [],
  editable = false,
  onMarkersChange,
}: ImageMapWidgetProps) {
  const containerRef = useRef<HTMLDivElement>(null);
  const [, setDimensions] = useState({ width: 0, height: 0 });
  const [imageLoaded, setImageLoaded] = useState(false);
  const [imageError, setImageError] = useState(false);
  const [resolvedImageUrl, setResolvedImageUrl] = useState<string>();

  useEffect(() => {
    let active = true;
    if (!backgroundImageUrl) return () => { active = false; };
    api.get(backgroundImageUrl, { responseType: "blob" }).then(response => {
      if (!active) return;
      const url = URL.createObjectURL(response.data);
      setResolvedImageUrl(url);
    }).catch(() => { if (active) setImageError(true); });
    return () => { active = false; };
  }, [backgroundImageUrl]);

  // Update dimensions on resize
  useEffect(() => {
    if (!containerRef.current) return;

    const observer = new ResizeObserver((entries) => {
      for (const entry of entries) {
        setDimensions({
          width: entry.contentRect.width,
          height: entry.contentRect.height,
        });
      }
    });

    observer.observe(containerRef.current);
    return () => observer.disconnect();
  }, []);

  // Resolve markers with device data
  const resolvedMarkers: MarkerWithData[] = markers
    .filter((marker) => {
      // Validate normalized coordinates
      return (
        marker.x >= 0 &&
        marker.x <= 1 &&
        marker.y >= 0 &&
        marker.y <= 1
      );
    })
    .map((marker) => {
      const device = marker.deviceId
        ? devices.find((d) => d.id === marker.deviceId)
        : undefined;
      return {
        x: marker.x,
        y: marker.y,
        device,
        label: marker.label || device?.name,
      };
    })
    .filter((marker) => marker.device || marker.label); // Only show if we have data

  if (!backgroundImageUrl) {
    return (
      <div role="img" aria-label="Device floorplan with overlay markers" className="flex h-full min-h-48 items-center justify-center rounded-md border border-[var(--ds-border-subtle)] bg-[var(--ds-surface-muted)] text-sm text-[var(--ds-text-muted)]">
        No background image configured
      </div>
    );
  }

  if (imageError) {
    return (
      <div role="img" aria-label="Device floorplan with overlay markers" className="flex h-full min-h-48 items-center justify-center rounded-md border border-[var(--ds-border-subtle)] bg-[var(--ds-surface-muted)] text-sm text-[var(--ds-text-muted)]">
        Background image unavailable
      </div>
    );
  }

  const updateMarker = (id: string, event: ReactPointerEvent<HTMLDivElement>) => {
    if (!editable || !onMarkersChange || !containerRef.current) return;
    event.currentTarget.setPointerCapture(event.pointerId);
    const move = (next: PointerEvent) => {
      const rect = containerRef.current!.getBoundingClientRect();
      const x = Math.max(0, Math.min(1, (next.clientX - rect.left) / rect.width));
      const y = Math.max(0, Math.min(1, (next.clientY - rect.top) / rect.height));
      onMarkersChange(markers.map(marker => marker.id === id ? { ...marker, x, y } : marker));
    };
    const done = () => { window.removeEventListener("pointermove", move); window.removeEventListener("pointerup", done); };
    window.addEventListener("pointermove", move); window.addEventListener("pointerup", done, { once: true });
  };

  return (
    <div
      ref={containerRef}
      className="relative h-full min-h-48 w-full overflow-hidden rounded-md border border-[var(--ds-border-subtle)] bg-[var(--ds-surface-muted)]"
      role="img"
      aria-label="Device floorplan with overlay markers"
    >
      {editable && <div className="absolute right-2 top-2 z-10 flex gap-1 rounded bg-[var(--ds-surface-raised)] p-1 shadow"><button type="button" className="rounded border px-2 py-1 text-[10px]" onClick={() => { const device = devices[0]; onMarkersChange?.([...markers, { id: `marker-${Date.now()}`, x: .5, y: .5, deviceId: device?.id, label: device?.name ?? "Marker" }]); }}>Add marker</button></div>}
      {/* Background Image */}
      <img
        src={resolvedImageUrl}
        alt="Floorplan background"
        className="h-full w-full object-contain"
        onLoad={() => setImageLoaded(true)}
        onError={() => setImageError(true)}
        style={{ opacity: imageLoaded ? 1 : 0 }}
      />

      {/* Markers */}
      {imageLoaded &&
        resolvedMarkers.map((marker, index) => {
          return (
            <div
              key={`${marker.device?.id || index}-${marker.x}-${marker.y}`}
              className="absolute -translate-x-1/2 -translate-y-1/2"
              style={{
                left: `${marker.x * 100}%`,
                top: `${marker.y * 100}%`,
              }}
            >
              {/* Marker Pin */}
              <div
                onPointerDown={(event) => updateMarker(markers[index]?.id ?? "", event)}
                className="h-4 w-4 rounded-full border-2 border-white shadow-md"
                style={{
                  backgroundColor:
                    marker.device?.status === "online"
                      ? "#10b981"
                      : "#64748b",
                }}
                title={
                  marker.device
                    ? `${marker.device.name} - ${marker.device.status}`
                    : marker.label
                }
              />

              {editable && <button type="button" aria-label={`Remove ${marker.label ?? "marker"}`} className="absolute -right-2 -top-2 rounded-full bg-red-500 px-1 text-[9px] text-white" onClick={() => onMarkersChange?.(markers.filter(item => item.id !== markers[index]?.id))}>×</button>}

              {/* Label */}
              {marker.label && (
                <div className="mt-1 rounded bg-[var(--ds-surface-raised)] px-2 py-1 text-[10px] shadow-sm">
                  {marker.label}
                  {marker.device && (
                    <span className="ml-1 text-[var(--ds-text-muted)]">
                      {marker.device.status}
                    </span>
                  )}
                </div>
              )}
            </div>
          );
        })}
    </div>
  );
}
