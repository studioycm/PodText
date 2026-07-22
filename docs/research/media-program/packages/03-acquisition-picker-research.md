# Package 3 Forecast Research — Acquisition

Status: future-only; fresh Simplifier audit required.

Upload, URL, Spotify and managed-storage inputs have different transport risks
but can converge after bytes are acquired. Gallery selection is not acquisition
and must not copy or normalize existing media. Spotify is a URL producer, not a
fifth source.

MIME/extension checks, configurable upload limits and filename cleanup belong
at new-input admission. URL acquisition retains existing SSRF, redirect, DNS
and response-size controls. SVG must be sanitized before inline rendering.
Raster normalization and checksums are not mandatory admission or visibility
requirements.
