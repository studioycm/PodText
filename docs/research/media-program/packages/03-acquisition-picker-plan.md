# Package 3 Forecast Plan — Acquisition

Status: not approved.

After a fresh audit:

1. Retain Gallery as selection of an existing row without file mutation.
2. Route Upload, URL and Storage through focused acquisition handlers with a
   shared new-input validator.
3. Feed Spotify thumbnails into the URL handler.
4. Keep network/filesystem acquisition outside importer transactions.
5. Add reusable SVG sanitation before inline delivery.
6. Use configured storage roots and opaque server-side candidates for Storage.
7. Cover HTTP with committed fixtures and `Http::preventStrayRequests()`.

Do not require normalization/checksum proof for visibility or selection.
