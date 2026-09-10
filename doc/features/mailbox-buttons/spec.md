# Mailbox Buttons

## Problem

Mailbox authors need a clear call to action in HTML emails. A personal page URL can remain a normal link, or an author can present a link as a branded email button with custom text.

## Solution

Add button syntax to composed Markdown email bodies:

- `:button[Button text](target)` renders a branded email button.
- `:button:link` renders a branded button for the resolved personal link with the default localized label.
- `:button[Button text](:link)` renders a branded button for the resolved personal link with custom text.

Keep `:link` as a normal URL link. Keep regular Markdown links unchanged. Button targets can be normal URLs or supported placeholders.

## Key Decisions

- Use a `:button[...]()` Markdown extension syntax so the feature works for any link target.
- Require button text in the explicit syntax so authors control the call to action.
- Keep `:button:link` as a shorthand for the common personal-link case.
- Resolve placeholders before button rendering so `:link` works as a button target.
- Reuse the existing branded email button partial and theme colors.
- Render buttons in the existing composed email pipeline so previews, stored messages, and sent messages match.

## Non-goals / Scope Boundaries

- Do not convert ordinary Markdown links into buttons.
- Do not change login-code emails.
- Do not add a visible raw URL beside a button.
- Do not add a separate plain-text multipart email in this change.
- Do not add button alignment, size, or color options.
