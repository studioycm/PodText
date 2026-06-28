# Phase 02 Media Embeds Guideline

- Store URLs and provider metadata only.
- Never store or render arbitrary iframe HTML.
- Embed URLs must be HTTPS and host-allowlisted.
- Render embeds through the application Blade component.
- If an embed is missing or rejected, render the original source link.
- Do not fetch remote covers/media during imports.
- Metadata extraction must be explicit admin behavior, not automatic background behavior.
