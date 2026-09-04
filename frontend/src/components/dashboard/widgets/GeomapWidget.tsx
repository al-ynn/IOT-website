import { useEffect, useRef, useState } from "react";
import * as maplibregl from "maplibre-gl";
import "maplibre-gl/dist/maplibre-gl.css";
import type { Device } from "../../../types/device";

interface GeomapWidgetProps {
  devices: Device[];
}

interface MarkerData {
  id: string;
  name: string;
  status: string;
  latitude: number;
  longitude: number;
  locationName?: string;
  telemetry?: Record<string, unknown>;
}

export default function GeomapWidget({ devices }: GeomapWidgetProps) {
  const mapContainer = useRef<HTMLDivElement>(null);
  const map = useRef<maplibregl.Map | null>(null);
  const markers = useRef<maplibregl.Marker[]>([]);
  const [error, setError] = useState<string>("");

  useEffect(() => {
    if (!mapContainer.current || map.current) return;

    // Get map style URL from environment or use default OSM-based style
    const styleUrl = import.meta.env.VITE_MAP_STYLE_URL || "https://demotiles.maplibre.org/style.json";

    try {
      map.current = new maplibregl.Map({
        container: mapContainer.current,
        style: styleUrl,
        center: [0, 0],
        zoom: 1,
        attributionControl: {},
      });

      map.current.addControl(new maplibregl.NavigationControl(), "top-right");

      map.current.on("error", () => {
        setError("Map provider unavailable");
      });
    } catch {
      window.setTimeout(() => setError("Map initialization failed"), 0);
    }

    return () => {
      markers.current.forEach((marker) => marker.remove());
      markers.current = [];
      map.current?.remove();
      map.current = null;
    };
  }, []);

  useEffect(() => {
    if (!map.current || !devices.length) return;

    // Clear existing markers
    markers.current.forEach((marker) => marker.remove());
    markers.current = [];

    // Filter devices with valid coordinates
    const validMarkers: MarkerData[] = devices
      .filter((device) => {
        const lat = device.location?.latitude;
        const lon = device.location?.longitude;
        return (
          typeof lat === "number" && typeof lon === "number" &&
          Number.isFinite(lat) && Number.isFinite(lon) &&
          lat >= -90 && lat <= 90 && lon >= -180 && lon <= 180
        );
      })
      .map((device) => ({
        id: device.id ?? `${device.name}-${device.serialNumber}`,
        name: device.name,
        status: device.status ?? "offline",
        latitude: device.location!.latitude!,
        longitude: device.location!.longitude!,
        locationName: device.location?.name,
      }));

    if (validMarkers.length === 0) return;

    // Create bounds to fit all markers
    const bounds = new maplibregl.LngLatBounds();

    validMarkers.forEach((markerData) => {
      const { latitude, longitude, name, status, locationName } = markerData;

      // Create marker element
      const el = document.createElement("div");
      el.className = "custom-marker";
      el.style.width = "16px";
      el.style.height = "16px";
      el.style.borderRadius = "50%";
      el.style.border = "2px solid white";
      el.style.boxShadow = "0 2px 4px rgba(0,0,0,0.3)";
      el.style.cursor = "pointer";
      el.style.backgroundColor =
        status === "online" ? "#10b981" : "#64748b";

      // Create popup content
      const popupContent = document.createElement("div");
      popupContent.style.fontSize = "12px";
      popupContent.style.lineHeight = "1.5";
      
      const deviceNameEl = document.createElement("strong");
      deviceNameEl.textContent = name;
      popupContent.appendChild(deviceNameEl);
      
      if (locationName) {
        const locationEl = document.createElement("div");
        locationEl.textContent = locationName;
        locationEl.style.color = "#64748b";
        popupContent.appendChild(locationEl);
      }
      
      const statusEl = document.createElement("div");
      statusEl.textContent = `Status: ${status}`;
      statusEl.style.marginTop = "4px";
      popupContent.appendChild(statusEl);

      const popup = new maplibregl.Popup({
        offset: 25,
        closeButton: false,
      }).setDOMContent(popupContent);

      const marker = new maplibregl.Marker({ element: el })
        .setLngLat([longitude, latitude])
        .setPopup(popup)
        .addTo(map.current!);

      markers.current.push(marker);
      bounds.extend([longitude, latitude]);
    });

    // Fit map to markers with padding
    if (validMarkers.length === 1) {
      map.current.setCenter([
        validMarkers[0].longitude,
        validMarkers[0].latitude,
      ]);
      map.current.setZoom(12);
    } else {
      map.current.fitBounds(bounds, {
        padding: 50,
        maxZoom: 15,
      });
    }
  }, [devices]);

  if (error) {
    return (
      <div role="img" aria-label="Geographic map of authorized Device locations" className="flex h-full min-h-48 items-center justify-center rounded-md border border-[var(--ds-border-subtle)] bg-[var(--ds-surface-muted)] text-sm text-[var(--ds-text-muted)]">
        {error}
      </div>
    );
  }

  return (
    <div
      ref={mapContainer}
      className="h-full min-h-48 w-full rounded-md"
      role="img"
      aria-label="Geographic map of authorized Device locations"
    />
  );
}
