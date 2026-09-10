# Email Logo — Spec

## Problem

Branded HTML emails show the application name in the header but do not show
the application's logo. Recipients therefore see less of the product's
visual identity than they see in the application.

## Solution

Add the standard application logo to the shared HTML email header. Place the
logo beside the existing application name in one compact row. Keep the
application name as text so the brand remains identifiable when email
clients block images. The logo is branding only and is not a link.

## Key Decisions

- Add the logo to the shared HTML email layout so every HTML email using that
  layout receives the same header.
- Use the standard application logo, not the optional email-specific logo.
- Place the logo beside the existing application name.
- Keep the existing application name as visible text and as the logo's
  accessible alternative text.
- Do not make the logo clickable.
- Preserve the existing email color tokens and layout styling.

## Non-goals

- Add a logo to plain-text email messages.
- Change the application's logo upload or theme-builder behavior.
- Add a separate email-specific logo setting.
- Change email footer branding or message content.