# Caliber Learnings

Accumulated patterns and anti-patterns from development sessions.
Auto-managed by [caliber](https://github.com/caliber-ai-org/ai-setup) — do not edit manually.

- **[gotcha]** `src/Plugin.php` is a large file — reading it without a `limit` parameter returns an empty `{}` response. Read it in chunks using `limit: 120` on the first call, then subsequent calls with increasing `offset` values to retrieve the full file content.
